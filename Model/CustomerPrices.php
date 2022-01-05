<?php

namespace Ripen\Prophet21\Model;

use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote\Item;
use Ripen\Prophet21\Model\Products as P21Products;

class CustomerPrices
{
    /**
     * @var P21Products
     */
    protected $p21Products;

    /**
     * @param P21Products $p21Products
     */
    public function __construct(
        P21Products $p21Products
    ) {
        $this->p21Products = $p21Products;
    }

    /**
     * @param CartInterface $quote
     * @return void
     */
    public function applyP21PricingToQuote(CartInterface $quote)
    {
        $items = $quote->getAllItems();

        // Don't do further processing if cart is empty. Note that we have to use count() on return of getAllItems()
        // rather than $quote->getItemsCount() as the latter is not updated consistently.
        if (! count($items)) {
            return;
        }

        // Calculate pricing for a given combination of products ordered together, taking into account price families.
        $quantities = $skus = [];
        /** @var Item $item */
        foreach ($items as $item) {
            if ($item->hasSkipP21Price()) {
                continue;
            }
            $skus[] = $item->getSku();
            $quantities[] = $item->getQty();
        }
        $priceData = array_column(
            $this->p21Products->productCustomerPrices($quote->getCustomer()->getId(), $skus, $quantities),
            null,
            'sku'
        );

        // For every item ordered, parse the custom prices and set prices as applicable
        /** @var Item $item */
        foreach ($items as $item) {
            if ($item->hasSkipP21Price()) {
                continue;
            }
            $customPrice = $priceData[$item->getSku()]['unitPrice'] ?? null;

            if ($item->getPrice() != $customPrice) {
                $item->setOriginalCustomPrice($customPrice);
                $item->setCustomPrice($customPrice);
            }
        }
    }
}
