<?php

namespace Ripen\Prophet21\Helper;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session;
use Magento\Customer\Model\SessionFactory;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Ripen\Prophet21\Helper\Customer as CustomerHelper;

class WholesaleHelper extends AbstractHelper
{
    /**
     * @var Session
     */
    protected $customerSession;

    /**
     * @var CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var Customer
     */
    protected $customerHelper;

    /**
     * WholesaleHelper constructor.
     * @param Context $context
     * @param SessionFactory $customerSession
     * @param CustomerRepositoryInterface $customerRepository
     * @param ScopeConfigInterface $scopeConfig
     * @param Customer $customerHelper
     */
    public function __construct(
        Context $context,
        SessionFactory $customerSession,
        CustomerRepositoryInterface $customerRepository,
        ScopeConfigInterface $scopeConfig,
        CustomerHelper $customerHelper
    ) {
        parent::__construct($context);

        $this->customerSession = $customerSession->create();
        $this->customerRepository = $customerRepository;
        $this->scopeConfig = $scopeConfig;
        $this->customerHelper = $customerHelper;
    }

    /**
     * @return bool
     */
    public function isLoggedInCustomerWholesale()
    {
        $customer = $this->customerSession->getCustomer();
        if (! $customer->getId()) {
            return false;
        }
        return $this->isCustomerWholesale($customer);
    }

    /**
     * @param \Magento\Customer\Model\Customer $customer
     * @return bool
     */
    public function isCustomerWholesale($customer)
    {
        $p21CustomerId = $this->customerHelper->getP21CustomerId($customer);
        $retailCustomerId = $this->customerHelper->getRetailP21CustomerId();
        return $p21CustomerId && $p21CustomerId != $retailCustomerId;
    }
}
