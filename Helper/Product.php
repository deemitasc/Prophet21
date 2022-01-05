<?php

namespace Ripen\Prophet21\Helper;

class Product extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * @var \Magento\InventoryApi\Api\GetSourceItemsBySkuInterface
     */
    protected $getSourceItemsBySku;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Magento\Catalog\Api\ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\InventoryApi\Api\GetSourceItemsBySkuInterface $getSourceItemsBySku
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\InventoryApi\Api\GetSourceItemsBySkuInterface $getSourceItemsBySku,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
    ) {
        $this->getSourceItemsBySku = $getSourceItemsBySku;
        $this->scopeConfig = $scopeConfig;
        $this->productRepository = $productRepository;

        parent::__construct($context);
    }

    /**
     * @param $product
     * @return string
     */
    public function getProductAvailability($product)
    {
        $stockQty = 0;
        $sourceItems = $this->getSourceItemsBySku->execute($product->getSku());
        foreach ($sourceItems as $sourceItem) {
            $stockQty += $sourceItem->getQuantity();
        }

        return $this->getCappedStockDisplay($stockQty, true, $product);
    }

    /**
     * @param int $stockQty
     * @param bool $showOutOfStockMsg
     * @param $product
     * @return string
     */
    public function getCappedStockDisplay($stockQty, $showOutOfStockMsg = false, $product = null)
    {
        $inventoryDisplayLimit = $this->scopeConfig->getValue('p21/integration/inventory_display_limit');

        if(!empty($product)){
            if($product->getStockDisplayThreshold()){
                $inventoryDisplayLimit = $product->getStockDisplayThreshold();
            }
        }

        if ($stockQty > $inventoryDisplayLimit) {
            $stockQty = $inventoryDisplayLimit . '+';
        } elseif ($stockQty <= 0 && $showOutOfStockMsg) {
            $stockQty = 'Out of Stock';
        }

        return $stockQty;
    }

    /**
     * Fetch additional product data. Used in case product instances are being cached and do not contain these
     * additional data at the time they were cached
     * @TODO: move this function out of magento2-prophet21, in order to avoid making this module a "kitchen drawer"
     *
     * @param \Magento\Catalog\Model\Product|int $product
     * @param string|null $key
     * @return array|mixed|null
     */
    public function getProductAdditionalData($product, $key = null)
    {
        // if the product instance already has the info requested, simply return it
        if (! is_null($key)) {
            $info = $product->getData($key);
            if (!empty($info)) {
                return $info;
            }
        }

        try {
            /** @var $productFromRepo \Magento\Catalog\Model\Product */
            if (is_numeric($product)) {
                $productFromRepo = $this->productRepository->getById($product);
            } else {
                $productFromRepo = $this->productRepository->getById($product->getId());
            }

            $return = [
                'short_description' => $productFromRepo->getData('short_description'),
                'description' => $productFromRepo->getData('description'),
            ];

            if (is_null($key)) {
                return $return;
            }

            if (array_key_exists($key, $return)) {
                return $return[$key];
            }

            return null;
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return null;
        }
    }

    /**
     * @return bool
     */
    public function isUOMSelectionEnabled()
    {
        return (bool) $this->scopeConfig->getValue('p21/integration/uom_configuration/enable_uom_selection');
    }

    /**
     * @return array
     */
    protected function getGloballyDisabledUOM()
    {
        $UOM = $this->scopeConfig->getValue('p21/integration/uom_configuration/disabled_uom');
        $UOM = explode(',', $UOM);
        $UOM = array_map('trim', $UOM);

        return $UOM;
    }

    /**
     * @param \Magento\Catalog\Model\Product $product
     * @return array
     */
    public function getAvailableProductUOM($product)
    {
        $availableUOM = $product->getData('p21_available_product_uom');
        $availableUOM = explode(',', $availableUOM);

        $availableUOMValues = [];
        foreach($availableUOM as $value) {
            $UOMPair =  $this->parseUOMSizePairString($value);
            $availableUOMValues[$UOMPair['uom']] = $UOMPair;
        }

        $globallyDisabledUOM = $this->getGloballyDisabledUOM();
        $disabledUOM = $this->getProductDisabledUOM($product);
        $UOMToFilter = array_merge($globallyDisabledUOM, $disabledUOM);

        foreach($UOMToFilter as $filter) {
            unset($availableUOMValues[$filter]);
        }

        return $availableUOMValues;
    }

    /**
     * @param $UOMPairString
     * @return array
     */
    public function parseUOMSizePairString($UOMPairString)
    {
        $UOMPairString = trim($UOMPairString);
        if (! empty($UOMPairString)) {
            list($UOMValue, $UOM) = explode(\Ripen\Prophet21\Model\Products::PAIRED_VALUES_DELIMITER, $UOMPairString);
        }
        return [
            'uom' => $UOMValue ?? '',
            'size' => $UOM ?? '',
            'value_string' => $UOMPairString,
        ];
    }

    /**
     * @param $UOMPairString
     * @return mixed|null
     */
    public function getUOMValueFromString($UOMPairString)
    {
        $parsedValues = $this->parseUOMSizePairString($UOMPairString);

        return $parsedValues['uom'] ?? null;
    }

    /**
     * @param $UOMPairString
     * @return mixed|null
     */
    public function getUOMSizeFromString($UOMPairString)
    {
        $parsedValues = $this->parseUOMSizePairString($UOMPairString);

        return $parsedValues['size'] ?? null;
    }

    /**
     * @param \Magento\Catalog\Model\Product $product
     * @return array
     */
    protected function getProductDisabledUOM($product)
    {
        $UOM = $product->getData('hidden_uom_list');
        $UOM = explode(',', $UOM);
        $UOM = array_map('trim', $UOM);

        return $UOM;
    }

    /**
     * @param \Magento\Catalog\Model\Product $product
     * @return array|null
     */
    public function getProductDefaultUOM($product)
    {
        $defaultSellingUnit = $product->getData('p21_default_selling_unit');

        $availableUOM = $this->getAvailableProductUOM($product);

        return array_key_exists($defaultSellingUnit, $availableUOM) ? $availableUOM[$defaultSellingUnit] : null;
    }
}
