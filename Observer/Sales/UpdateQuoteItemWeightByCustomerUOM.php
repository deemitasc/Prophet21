<?php

namespace Ripen\Prophet21\Observer\Sales;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Catalog\Model\Product;
use Magento\Quote\Model\Quote\Item;
use Ripen\Prophet21\Helper\Product as P21ProductHelper;
use Psr\Log\LoggerInterface;

class UpdateQuoteItemWeightByCustomerUOM implements ObserverInterface
{
    /**
     * @var P21ProductHelper
     */
    protected $p21ProductHelper;

    /**
     * @param P21ProductHelper $p21ProductHelper
     */
    public function __construct(
        P21ProductHelper $p21ProductHelper
    ) {
        $this->p21ProductHelper = $p21ProductHelper;
    }

    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer) {
        /** @var Product $product */
        $product = $observer->getData('product');
        /** @var Item $quoteItem */
        $quoteItem = $observer->getData('quote_item');

        // ensure the quote item weight is accurate per selected UOM
        $buyRequestCartUOM = $quoteItem->getBuyRequest()->getCartUom();
        if (! empty($buyRequestCartUOM)) {
            $UOMSize = $this->p21ProductHelper->getUOMSizeFromString($buyRequestCartUOM);
            $quoteItem->setWeight($product->getWeight() * (float)$UOMSize);
        }
    }
}
