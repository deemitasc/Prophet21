<?php

namespace Ripen\Prophet21\Model;


use Magento\Quote\Api\CartItemRepositoryInterface;

class CustomerUOM
{
    /**
     * @var \Magento\Framework\App\RequestInterface
     */
    protected $request;

    /**
     * @var \Ripen\Prophet21\Helper\Product
     */
    protected $p21ProductHelper;

    /**
     * @var \Magento\Framework\Serialize\Serializer\Json
     */
    protected $serializer;

    const UOM_ITEM_KEY_NAME = 'uom';
    const UOM_FORM_FIELD_NAME = 'cart_uom';

    /**
     * CustomerUOM constructor.
     * @param \Magento\Framework\App\RequestInterface $request
     * @param \Ripen\Prophet21\Helper\Product $p21ProductHelper
     * @param \Magento\Framework\Serialize\Serializer\Json $serializer
     * @param \psr\Log\LoggerInterface $logger
     */
    public function __construct(
        \Magento\Framework\App\RequestInterface $request,
        \Ripen\Prophet21\Helper\Product $p21ProductHelper,
        \Magento\Framework\Serialize\Serializer\Json $serializer,
        \psr\Log\LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->p21ProductHelper = $p21ProductHelper;
        $this->serializer = $serializer;
        $this->logger = $logger;
    }

    /**
     * @param \Magento\Quote\Api\Data\CartInterface $quote
     */
    public function applyCustomerUOMToQuote(\Magento\Quote\Api\Data\CartInterface $quote)
    {
        if (! $this->p21ProductHelper->isUOMSelectionEnabled()) {
            return;
        }

        $items = $quote->getAllItems();

        // Don't do further processing if cart is empty. Note that we have to use count() on return of getAllItems()
        // rather than $quote->getItemsCount() as the latter is not updated consistently.
        if (! count($items)) {
            return;
        }

        $this->applyCustomerUOMToQuoteItems($items);
    }

    /**
     * @param array $items
     */
    public function applyCustomerUOMToQuoteItems($items)
    {
        if (! $this->p21ProductHelper->isUOMSelectionEnabled()) {
            return;
        }

        $productId = $this->request->getParam('product');
        $cartUOM = $this->request->getParam(self::UOM_FORM_FIELD_NAME);

        if (! empty($productId)) {
            if (! empty($cartUOM)) {
                $UOMPair = $this->p21ProductHelper->parseUOMSizePairString($cartUOM);
            }

            /** @var \Magento\Quote\Model\Quote\Item $item */
            foreach ($items as $item) {
                $product = $item->getProduct();
                if ($product->getId() !== $productId) {
                    continue;
                }

                if (empty($cartUOM)) {
                    $defaultUOM = $this->p21ProductHelper->getProductDefaultUOM($product);
                    if (! is_null($defaultUOM)) {
                        $cartUOM = $defaultUOM['value_string'];
                        $UOMPair = $this->p21ProductHelper->parseUOMSizePairString($cartUOM);
                    }
                }

                if (! empty($cartUOM)) {
                    // update the quote item UOM and buy request
                    if ($item->getData(self::UOM_ITEM_KEY_NAME) != $UOMPair['uom']) {
                        $item->setData(self::UOM_ITEM_KEY_NAME, $UOMPair['uom']);

                        // update buy request if necessary
                        $buyRequestCartUOM = $item->getBuyRequest()->getCartUom();
                        if (! empty($buyRequestCartUOM) && $buyRequestCartUOM != $cartUOM) {
                            $buyRequest = $this->serializer->unserialize($item->getOptionByCode('info_buyRequest')->getValue());
                            $buyRequest[self::UOM_FORM_FIELD_NAME] = $cartUOM;
                            $item->getOptionByCode('info_buyRequest')->setValue($this->serializer->serialize($buyRequest));
                            $item->saveItemOptions();
                        }
                    }

                    // update the quote item price
                    $customPrice = $product->getPrice() * (float)$UOMPair['size'];
                    if ($item->getCustomPrice() != $customPrice) {
                        $item->setOriginalCustomPrice($customPrice);
                        $item->setCustomPrice($customPrice);
                    }
                }
            }
        }
    }

    /**
     * @param $qty
     * @param $cartUOM
     * @return float|int
     */
    public function calculateQtyFromCartUOM($qty, $cartUOM)
    {
        $UOMSize = $this->p21ProductHelper->getUOMSizeFromString($cartUOM);
        if (is_numeric($UOMSize) && $UOMSize > 0) {
            $qty = $qty * $UOMSize;
        }

        return $qty;
    }
}
