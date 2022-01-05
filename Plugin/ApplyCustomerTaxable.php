<?php

namespace Ripen\Prophet21\Plugin;

class ApplyCustomerTaxable
{
    /**
     * @var \Ripen\Prophet21\Helper\Customer
     */
    protected $customerHelper;

    public function __construct(
        \Ripen\Prophet21\Helper\Customer $customerHelper
    ) {
        $this->customerHelper = $customerHelper;
    }

    /**
     * @param \Magento\Tax\Model\Sales\Total\Quote\Tax $subject
     * @param callable $proceed
     * @param $quote
     * @param $shippingAssignment
     * @param $total
     * @return \Magento\Tax\Model\Sales\Total\Quote\Tax
     */
    public function aroundCollect(
        \Magento\Tax\Model\Sales\Total\Quote\Tax $subject,
        callable $proceed,
        $quote,
        $shippingAssignment,
        $total
    ) {
        $customerId = $quote->getCustomer()->getId();
        if (! $this->customerHelper->isTaxable($customerId)) {
            $total->setTaxAmount(0);
            $returnValue = $subject;
        } else {
            $returnValue = $proceed($quote, $shippingAssignment, $total);
        }

        return $returnValue;
    }
}
