<?php

namespace Ripen\Prophet21\Block\Customer\Account\Dashboard;

class SalesRep extends \Magento\Framework\View\Element\Template
{
    /**
     * @var \Ripen\Prophet21\Helper\Customer
     */
    protected $customerHelper;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Ripen\Prophet21\Helper\Customer $customerHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->customerHelper = $customerHelper;
    }

    /**
     * Check if logged in user is a Sales Rep and can perform Sales Rep functionality
     * @return bool
     */
    public function isSalesRepFunctionEnabled()
    {
        try {
            return $this->customerHelper->isLoggedInCustomerASalesRep();
        }
        catch (\Exception $e) {
            // gracefully fail
            return false;
        }
    }
}
