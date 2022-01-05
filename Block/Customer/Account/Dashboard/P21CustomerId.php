<?php

namespace Ripen\Prophet21\Block\Customer\Account\Dashboard;

class P21CustomerId extends \Magento\Framework\View\Element\Template
{
    /**
     * @var \Ripen\Prophet21\Helper\Customer
     */
    protected $customerHelper;

    const FORM_FIELD_SELECTED_P21_CUSTOMER_ID = 'selected_customer_id';

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Ripen\Prophet21\Helper\Customer $customerHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->customerHelper = $customerHelper;
    }

    /**
     * @return int|null
     */
    public function getActiveP21CustomerId()
    {
        $customer = $this->customerHelper->getCurrentCustomer();

        return (! is_null($customer)) ? $this->customerHelper->getP21CustomerId($customer) : null;
    }

    /**
     * @return string|null
     */
    public function getActiveP21CustomerName()
    {
        $customer = $this->customerHelper->getCurrentCustomer();

        return (! is_null($customer)) ? $this->customerHelper->getP21CustomerName($customer) : null;
    }

    /**
     * @return array|null
     */
    public function getP21CustomerIds()
    {
        $customer = $this->customerHelper->getCurrentCustomer();

        return (! is_null($customer)) ? $this->customerHelper->getP21CustomerIds($customer) : null;
    }

    /**
     * Return the Url for saving.
     *
     * @return string
     */
    public function getSaveUrl()
    {
        return $this->_urlBuilder->getUrl(
            'prophet21/account/switchCustomerId'
        );
    }
}
