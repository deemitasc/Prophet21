<?php

namespace Ripen\Prophet21\Observer\Sales;


class SetSelectedUOMOnQuoteAdd implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * @var \Ripen\Prophet21\Model\CustomerUOM
     */
    protected $customerUOM;

    /**
     * @param \Ripen\Prophet21\Model\CustomerUOM $customerUOM
     */
    public function __construct(
      \Ripen\Prophet21\Model\CustomerUOM $customerUOM
    ) {
        $this->customerUOM = $customerUOM;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        return $this->customerUOM->applyCustomerUOMToQuoteItems($observer->getEvent()->getData('items'));
    }
}
