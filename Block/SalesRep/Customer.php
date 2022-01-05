<?php

namespace Ripen\Prophet21\Block\SalesRep;

class Customer extends \Magento\Framework\View\Element\Template
{
    /**
     * @var string
     */
    protected $_template = 'Ripen_Prophet21::sales-rep/customer.phtml';
    /**
     * @var \Ripen\SimpleApps\Model\Api
     */
    protected $api;

    /**
     * @var \Magento\Customer\Helper\Session\CurrentCustomer
     */
    protected $currentCustomer;

    /**
     * @var \Ripen\Prophet21\Helper\Customer
     */
    protected $customerHelper;

    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Ripen\SimpleApps\Model\Api $api
     * @param \Magento\Customer\Helper\Session\CurrentCustomer $currentCustomer
     * @param \Ripen\Prophet21\Helper\Customer $customerHelper
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Ripen\SimpleApps\Model\Api $api,
        \Magento\Customer\Helper\Session\CurrentCustomer $currentCustomer,
        \Ripen\Prophet21\Helper\Customer $customerHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->api = $api;
        $this->currentCustomer = $currentCustomer;
        $this->customerHelper = $customerHelper;
    }

    /**
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();
        $this->pageConfig->getTitle()->set(__('Sales Rep Assigned Customers'));
    }

    /**
     * @return array
     */
    public function getSalesRepAssignedCustomers()
    {
        if (!$this->currentCustomer->getCustomerId()) {
            return [];
        }

        $customerId = $this->currentCustomer->getCustomerId();

        try {
            $salesRepId = $this->customerHelper->getCustomersOwnP21SalesRepId($customerId);

            return $this->api->getSalesRepCustomersArray($salesRepId);
        }
        catch (\Magento\Framework\Exception\LocalizedException $e) {
            return [];
        }
    }

    /**
     * @return string
     */
    public function getBackUrl()
    {
        return $this->getUrl('customer/account/');
    }
}
