<?php

namespace Ripen\Prophet21\Controller\Inventory;

use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Controller\Result\JsonFactory;

class SelectSource extends \Magento\Framework\App\Action\Action
{
    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        JsonFactory $resultJsonFactory,
        \Magento\Quote\Model\ResourceModel\Quote\Item\CollectionFactory $quoteCollectionFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Quote\Model\ResourceModel\Quote\Item\Collection $quoteItemCollection,
        \Magento\Checkout\Model\Cart $cart,
        \Magento\Quote\Model\ResourceModel\Quote\Item\Option\Collection $options,
        \Magento\Quote\Model\Quote\ItemFactory $quoteItemFactory
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->quoteCollectionFactory = $quoteCollectionFactory;
        $this->checkoutSession = $checkoutSession;
        $this->quoteItemCollection = $quoteItemCollection;
        $this->cart = $cart;
        $this->options = $options;
        $this->quoteItemFactory = $quoteItemFactory;

        parent::__construct($context);
    }

    public function execute()
    {
        $data = $this->getRequest()->getPost();
        $result = $this->resultJsonFactory->create();

        if($this->getRequest()->isXmlHttpRequest() && $data) {
            
            $this->quoteItemCollection->addFieldToFilter('item_id', $data['item_id']);
            $quoteItem = $this->quoteItemCollection->getFirstItem();
            $quoteItem->addOption(
                array(
                'product_id' => $quoteItem->getProductId(),
                'code' => 'inventory_source',
                'value' => $data['inventory_source']
                )
            );
            $quoteItem->setData('additional_data', json_encode(['inventory_source'=>$data['inventory_source']]));
            $quoteItem->save();
        }

        $result->setData($data['source']);
    }
}
