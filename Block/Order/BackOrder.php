<?php

namespace Ripen\Prophet21\Block\Order;

class BackOrder extends \Magento\Sales\Block\Order\History
{
    /**
     * @var string
     */
    protected $_template = 'Ripen_Prophet21::order/backorder.phtml';

    const COMPLETED_ORDER_STATUSES = [
        'canceled', 'closed', 'complete'
    ];

    /**
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();
        $this->pageConfig->getTitle()->set(__('My Backorders'));
    }

    /**
     * @return bool|\Magento\Sales\Model\ResourceModel\Order\Collection
     */
    public function getOrders()
    {
        if (!($customerId = $this->_customerSession->getCustomerId())) {
            return false;
        }

        if (!$this->orders) {
            $openOrderStatuses = array_diff($this->_orderConfig->getVisibleOnFrontStatuses(), self::COMPLETED_ORDER_STATUSES);

            $this->orders = $this->_orderCollectionFactory->create($customerId)
                ->addFieldToSelect(
                '*'
                )->addFieldToFilter(
                    'status',
                    ['in' => $openOrderStatuses]
                )->setOrder(
                    'created_at',
                    'desc'
                );

            $this->orders->getSelect()
                ->join(
                    ['item' => 'sales_order_item'],
                    'item.order_id = main_table.entity_id',
                    []
                )
                ->where('item.qty_backordered > 0')
                ->where('item.qty_ordered - item.qty_shipped > 0')
                ->group('main_table.entity_id');
        }

        return $this->orders;
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @return null|string
     */
    public function getOrderPoNumber(\Magento\Sales\Api\Data\OrderInterface $order)
    {
        // first check if the po number was captured in the sales_order, delivered via extension attributes
        $poNumber = $order->getData('po_number');

        // fall back to po number on the payment if any
        if (empty($poNumber)) {
            $poNumber = $order->getPayment()->getPoNumber();
        }

        return $poNumber;
    }
}
