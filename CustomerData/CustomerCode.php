<?php
/**
 * P21 Customer Code section
 */

namespace Ripen\Prophet21\CustomerData;

class CustomerCode implements \Magento\Customer\CustomerData\SectionSourceInterface
{
    /**
     * @var \Magento\Customer\Helper\Session\CurrentCustomer
     */
    protected $currentCustomer;

    /**
     * @var \Ripen\Prophet21\Helper\Customer
     */
    protected $customerHelper;

    public function __construct(
        \Magento\Customer\Helper\Session\CurrentCustomer $currentCustomer,
        \Ripen\Prophet21\Helper\Customer $customerHelper
    ) {
        $this->currentCustomer = $currentCustomer;
        $this->customerHelper = $customerHelper;
    }

    public function getSectionData()
    {
        if (!$this->currentCustomer->getCustomerId()) {
            return [];
        }

        $customerId = $this->currentCustomer->getCustomerId();

        try {
            return [
                'code' => $this->customerHelper->getP21CustomerIdByMagentoCustomerId($customerId),
            ];
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            return [];
        }
    }
}
