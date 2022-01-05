<?php

namespace Ripen\Prophet21\Helper;

use Magento\Store\Model\ScopeInterface;
use Ripen\Prophet21\Helper\WholesaleHelper;

class MsiHelper extends \Magento\Framework\App\Helper\AbstractHelper
{

    /**
     * @var ProductData
     */
    protected $productData;

    /**
     * @var \Magento\InventoryApi\Api\GetSourceItemsBySkuInterface
     */
    protected $getSourceItemsBySku;

    /**
     * @var
     */
    protected $selectedSourceCode;

    /**
     * @var \Magento\InventoryApi\Api\SourceRepositoryInterface
     */
    protected $sourceRepositoryInterface;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Ripen\SimpleApps\Model\Api
     */
    protected $api;

    /**
     * @var \Ripen\Prophet21\Logger\Logger
     */
    protected $logger;

    /**
     * @var WholesaleHelper
     */
    protected $wholesaleHelper;

    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\InventoryApi\Api\GetSourceItemsBySkuInterface $getSourceItemsBySku,
        \Magento\InventoryApi\Api\SourceRepositoryInterface $sourceRepositoryInterface,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Ripen\SimpleApps\Model\Api $api,
        \Ripen\Prophet21\Logger\Logger $logger,
        WholesaleHelper $wholesaleHelper
    ) {
        $this->getSourceItemsBySku = $getSourceItemsBySku;
        $this->sourceRepositoryInterface = $sourceRepositoryInterface;
        $this->storeManager = $storeManager;
        $this->customerSession = $customerSession;
        $this->scopeConfig = $scopeConfig;
        $this->api = $api;
        $this->logger = $logger;
        $this->wholesaleHelper = $wholesaleHelper;

        parent::__construct($context);
    }

    /**
     * @param $stockQuantity
     * @param $sourceCode
     * @return int
     */
    public function getSourceQuantity($stockQuantity, $sourceCode)
    {
        $qty = 0;
        foreach ($stockQuantity['net'] as $locationId => $stock) {
            if ($locationId == $sourceCode) {
                $qty = $stock;
                break;
            }
        }
        return $qty;
    }

    /**
     * @param $stockQuantity
     * @param $sourceCode
     * @return int
     */
    public function getOtherSourcesQuantity($stockQuantity, $sourceCode)
    {
        $qty = 0;
        if(!empty($stockQuantity['net'])){
            foreach ($stockQuantity['net'] as $locationId => $stock) {
                if ($locationId != $sourceCode) {
                    $qty += $stock;
                }
            }
        }
        return $qty;
    }

    /**
     * @return int
     */
    public function getCustomerDefaultSourceCode()
    {
        $customer = $this->customerSession->getCustomer();
        $defaultCustomerWarehouse = $customer->getData('default_shipping_warehouse_id');
        if ($defaultCustomerWarehouse) {
            return $defaultCustomerWarehouse;
        }

        $defaultGlobalWarehouse = $this->scopeConfig->getValue('p21/feeds/default_inventory_source_id');
        return $defaultGlobalWarehouse;
    }

    /**
     * @param $sku
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getSourceItems($sku)
    {
        $filteredSourceItems = [];
        $sourceItems = $this->getSourceItemsBySku->execute($sku);
        foreach ($sourceItems as $sourceItem) {
            $source = $this->sourceRepositoryInterface->get($sourceItem->getSourceCode());
            if (! $source->isEnabled() || $source->getSourceCode() == 'default') {
                continue;
            }
            $filteredSourceItems[] = $sourceItem;
        }

        return $filteredSourceItems;
    }

    /**
     * @param $code
     * @return null|string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getSourceName($code)
    {
        $source = $this->sourceRepositoryInterface->get($code);
        return $source->getName();
    }

    /**
     * @param $sku
     * @param bool $isCategoryPage
     * @return array
     * @throws \Ripen\Prophet21\Exception\P21ApiException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getStockQuantity($sku, $isCategoryPage = false)
    {
        $useApiForCategory = $this->getUseApiStockStatusForCategory();
        $stockQuantity = [];
        if (!$isCategoryPage || ($isCategoryPage && $useApiForCategory)) {
            try {
                // Get stock quantities using API call
                $params = ['connect_timeout' => 1];
                $itemStock = $this->api->getItemStock($sku, $params);
                foreach ($itemStock as $stock) {
                    $key = intval($stock['location_id']);
                    $stockQuantity['net'][$key] = intval($this->api->calculateLocationNetStock($stock));
                    $stockQuantity['due'][$key] = intval($stock['order_quantity']);
                    $stockQuantity['stockable'][$key] = $stock['stockable'];
                }
                return $stockQuantity;
            } catch (\Exception $e) {
                // unable to fetch stock data with api call
                $this->logger->error($e->getMessage());
            }
        }

        // If API call fails or we are configured not to use the API, get stock quantities from Magento
        $stockQuantity = [];
        foreach ($this->getSourceItems($sku) as $sourceItem) {
            $key = intval($sourceItem->getSourceCode());
            $stockQuantity['net'][$key] = $sourceItem->getQuantity();
            $stockQuantity['due'][$key] = 'n/a';
        }

        return $stockQuantity;
    }

    /**
     * @param array $sourceItems
     * @return array
     */
    public function sortInventorySources($sourceItems)
    {
        $resortedSources = [];

        foreach ($sourceItems as $sourceItem) {
            if ($sourceItem->getSourceCode() == $this->getCustomerDefaultSourceCode()) {
                array_unshift($resortedSources, $sourceItem);
            } else {
                $resortedSources[] = $sourceItem;
            }
        }

        return $resortedSources;
    }

    /**
     * @param $sourceQty
     * @param \Magento\Catalog\Model\Product\Configuration\Item\ItemInterface $item
     * @return int
     */
    public function getSelectedSourceQuantity($sourceQty, $item)
    {
        return $sourceQty['net'][$this->getSelectedSourceCode($item)] ?? 0;
    }

    /**
     * @return string|null
     * @param \Magento\Catalog\Model\Product\Configuration\Item\ItemInterface $item
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getSelectedSourceName($item)
    {
        return $this->getSourceName($this->getSelectedSourceCode($item));
    }

    /**
     * @param \Magento\Catalog\Model\Product\Configuration\Item\ItemInterface $item
     * @return int
     */
    public function getSelectedSourceCode($item)
    {
        $selectedSource = $item->getOptionByCode('inventory_source');
        if ($selectedSource) {
            return (int) $selectedSource->getValue();
        }
        return $this->getCustomerDefaultSourceCode();
    }

    /**
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getSelectInventorySourceUrl()
    {
        return $this->storeManager->getStore()->getBaseUrl() . 'prophet21/inventory/selectsource';
    }

    /**
     * @return string
     */
    public function getAllowSourceSelection()
    {
        return $this->scopeConfig->getValue('p21/integration/allow_inventory_source_selection');
    }

    /**
     * @return string
     */
    public function getUseApiStockStatusForCategory()
    {
        return $this->scopeConfig->getValue('p21/integration/use_api_for_stock_status_on_category_pages', ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return bool
     */
    public function shouldRefreshStockPostLoad()
    {
        $refreshMode = $this->scopeConfig->getValue('p21/integration/stock_post_load', ScopeInterface::SCOPE_STORE);

        if ($refreshMode === 'all') {
            return true;
        }

        if ($refreshMode === 'wholesale' && $this->wholesaleHelper->isLoggedInCustomerWholesale()) {
            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function shouldShowStockPopup()
    {
        $popupMode = $this->scopeConfig->getValue('p21/integration/stock_popup', ScopeInterface::SCOPE_STORE);

        if ($popupMode === 'all') {
            return true;
        }

        if ($popupMode === 'wholesale' && $this->wholesaleHelper->isLoggedInCustomerWholesale()) {
            return true;
        }

        return false;
    }
}
