<?php
/**
 * Plugin to set UOM on punchout cart items via Greenwing Technology module.
 * Does nothing if the module is not present.
 */

namespace Ripen\Prophet21\Plugin\Greenwing;

use Greenwing\Technology\Block\Custom as GreenwingCartBlock;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\Quote\Model\Quote\Item as QuoteItem;

class SetCartItemProperties
{
    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(
        CheckoutSession $checkoutSession
    ) {
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * @param GreenwingCartBlock $subject
     * @param string $result
     * @return string
     */
    public function afterGetCartData(
        GreenwingCartBlock $subject,
        $result
    ) {
        $resultData = json_decode($result);
        $punchoutItems =& $resultData->request->request->body->items;
        if (! $punchoutItems) {
            return $result;
        }

        $quote = $this->checkoutSession->getQuote();
        $punchoutItemIndex = 0;
        foreach ($quote->getAllItems() as $quoteItem) {
            if ($quoteItem->getProductType() !== ProductType::TYPE_SIMPLE) {
                continue;
            }

            $itemUOM =
                $this->getSelectedUOM($quoteItem)
                ?: $quoteItem->getProduct()->getData('p21_default_selling_unit');
            if ($itemUOM) {
                $punchoutItems[$punchoutItemIndex]->UOM = $itemUOM;
            }
            ++$punchoutItemIndex;
        }

        return (string) json_encode($resultData);
    }

    /**
     * @param QuoteItem $quoteItem
     * @return string
     */
    protected function getSelectedUOM($quoteItem)
    {
        $selectedUOMData = $quoteItem->getBuyRequest()->getCartUom();
        return explode(':', $selectedUOMData)[0];
    }
}
