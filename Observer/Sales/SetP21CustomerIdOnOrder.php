<?php

namespace Ripen\Prophet21\Observer\Sales;

use Ripen\Prophet21\Helper\Customer as CustomerHelper;

class SetP21CustomerIdOnOrder implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * @var \Ripen\Prophet21\Helper\Customer
     */
    protected $customerHelper;

    public function __construct(
        CustomerHelper $customerHelper
    ) {
        $this->customerHelper = $customerHelper;
    }

    /**
     * Sets the P21 customer ID on the sales order before save, if it's not set already
     *
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $observer->getEvent()->getOrder();

        // If the P21 customer id is already set on the order, don't do anything
        if (! empty($order->getData(CustomerHelper::P21_CUSTOMER_ID_FIELD))) {
            return $this;
        }

        // Fetch the P21 customer id for the customer on the order
        $customerP21Id = $this->customerHelper->getP21CustomerIdByMagentoCustomerId($order->getCustomerId());

        $order->setData(CustomerHelper::P21_CUSTOMER_ID_FIELD, $customerP21Id);

        return $this;
    }
}
