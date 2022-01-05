<?php
namespace Ripen\Prophet21\Plugin;

use Magento\Quote\Api\CartAddingItemsTest;
use Magento\Quote\Api\CartItemRepositoryInterface;
use Magento\Quote\Model\Quote\Item\OptionFactory;

class UpdateQuoteQtyListOnCustomerUOM
{
    /**
     * @var \Ripen\Prophet21\Model\CustomerUOM
     */
    protected $customerUOM;

    /**
     * @var \Ripen\Prophet21\Helper\Product
     */
    protected $p21ProductHelper;

    /**
     * @var \Magento\Quote\Model\ResourceModel\Quote\Item\Option\CollectionFactory
     */
    protected $itemOptionCollectionFactory;

    /**
     * @var \Magento\Framework\Serialize\Serializer\Json
     */
    protected $serializer;

    public function __construct(
        \Ripen\Prophet21\Model\CustomerUOM $customerUOM,
        \Ripen\Prophet21\Helper\Product $p21ProductHelper,
        \Magento\Quote\Model\ResourceModel\Quote\Item\Option\CollectionFactory $itemOptionCollectionFactory,
        \Magento\Framework\Serialize\Serializer\Json $serializer
    ) {
        $this->customerUOM = $customerUOM;
        $this->p21ProductHelper = $p21ProductHelper;
        $this->itemOptionCollectionFactory = $itemOptionCollectionFactory;
        $this->serializer = $serializer;
    }

    public function beforeGetQty(
        \Magento\CatalogInventory\Model\Quote\Item\QuantityValidator\QuoteItemQtyList $subject,
        $productId, $quoteItemId, $quoteId, $itemQty
    ) {
        if ($this->p21ProductHelper->isUOMSelectionEnabled() && $itemQty > 0) {
            $quoteItemOption = $this->itemOptionCollectionFactory->create()
                ->addItemFilter($quoteItemId)
                ->addFilter('code','info_buyRequest')
                ->getItems();

            foreach($quoteItemOption as $option){
                $buyRequest = $this->serializer->unserialize($option->getData('value'));
                if (! empty($buyRequest[\Ripen\Prophet21\Model\CustomerUOM::UOM_FORM_FIELD_NAME])) {
                    $itemQty = $this->customerUOM->calculateQtyFromCartUOM($itemQty, $buyRequest[\Ripen\Prophet21\Model\CustomerUOM::UOM_FORM_FIELD_NAME]);
                }
            }
        }

        return [$productId, $quoteItemId, $quoteId, $itemQty];
    }
}
