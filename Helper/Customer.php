<?php

namespace Ripen\Prophet21\Helper;

use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Ripen\Prophet21\Exception\P21ApiException;

class Customer extends \Magento\Framework\App\Helper\AbstractHelper
{
    const CUSTOMER_CODE_DELIMITER = ',';
    const P21_CUSTOMER_ID_FIELD = 'erp_customer_id';
    const P21_CUSTOMER_ALTERNATE_IDS_FIELD = 'erp_customer_alternate_ids';

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;

    /**
     * @var \Magento\Customer\Api\CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * @var \Magento\Customer\Model\CustomerFactory
     */
    protected $customerFactory;

    /**
     * @var \Magento\Customer\Api\GroupRepositoryInterface
     */
    protected $groupRepository;

    /**
     * @var \Ripen\SimpleApps\Model\Api
     */
    protected $api;

    /**
     * @var \Ripen\Prophet21\Logger\Logger
     */
    protected $logger;

    public function __construct(
        Context $context,
        \Magento\Customer\Model\SessionFactory $customerSession,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\Customer\Api\GroupRepositoryInterface $groupRepository,
        \Ripen\SimpleApps\Model\Api $api,
        \Ripen\Prophet21\Logger\Logger $logger,
        \Ripen\Prophet21\Helper\MultistoreHelper $multistoreHelper

    )
    {
        parent::__construct($context);

        $this->customerSession = $customerSession->create();
        $this->customerRepository = $customerRepository;
        $this->customerFactory = $customerFactory;
        $this->groupRepository = $groupRepository;
        $this->api = $api;
        $this->logger = $logger;
        $this->multistoreHelper = $multistoreHelper;
    }

    /**
     * @return \Magento\Customer\Model\Customer|null
     */
    public function getCurrentCustomer()
    {
        if (!$this->customerSession->isLoggedIn()) {
            return null;
        }
        return $this->customerSession->getCustomer();
    }

    /**
     * Fetch a customer's P21 customer code using entity id
     *
     * @param int $customerId
     * @return int
     */
    public function getP21CustomerIdByMagentoCustomerId($customerId)
    {
        try {
            // TODO: Load with customer registry if possible, or if that doesn't work, note why here.
            $customer = $this->customerFactory->create()->load($customerId);
            $p21CustomerId = $this->getP21CustomerId($customer);
        } catch (NoSuchEntityException $e) {
            $p21CustomerId = null;
        }

        return $p21CustomerId ?: $this->getRetailP21CustomerId();
    }

    /**
     * Centralized method to fetch a customer's P21 Customer Code given a customer object
     *
     * @param \Magento\Customer\Model\Customer $customer
     * @return int|null
     */
    public function getP21CustomerId(\Magento\Customer\Model\Customer $customer)
    {
        $customerCode = $customer->getData(self::P21_CUSTOMER_ID_FIELD);
        return $customerCode ?: null;
    }

    /**
     * @param \Magento\Customer\Model\Customer $customer
     * @return array
     */
    public function getAlternateP21CustomerIds(\Magento\Customer\Model\Customer $customer)
    {
        $alternateCodesString = $customer->getData(self::P21_CUSTOMER_ALTERNATE_IDS_FIELD);

        if (empty($alternateCodesString)) {
            return [];
        }

        return explode(self::CUSTOMER_CODE_DELIMITER, $alternateCodesString);
    }

    /**
     * Returns all associated P21 customer IDs of a customer.
     *
     * @param \Magento\Customer\Model\Customer $customer
     * @return array
     */
    public function getP21CustomerIds(\Magento\Customer\Model\Customer $customer)
    {
        $returnValues = [];

        $primaryCode = $this->getP21CustomerId($customer);
        $alternateCodes = $this->getAlternateP21CustomerIds($customer);

        if (!empty($primaryCode)) {
            $returnValues = [$primaryCode];
        }

        $returnValues = array_merge($returnValues, $alternateCodes);

        sort($returnValues);

        return $returnValues;
    }

    /**
     * @param \Magento\Customer\Model\Customer $customer
     * @return null
     */
    public function getP21ContactId(\Magento\Customer\Model\Customer $customer)
    {
        $contactId = $customer->getData('erp_contact_id');
        $lookupInP21 = $this->scopeConfig->getValue('p21/integration/enable_p21_contact_lookup');
        $contacts = [];

        if (!$contactId && $lookupInP21) {
            try {
                $customerCode = $this->getP21CustomerId($customer);
                $contacts = $this->api->getCustomerContacts($customerCode);
            } catch (P21ApiException $e) {
                $this->logger->error($e->getMessage());
            }

            foreach ($contacts as $contact) {
                if ($this->api->parseCustomerContactEmailAddress($contact) == $customer->getEmail()) {
                    $contactId = $this->api->parseCustomerContactId($contact);
                    break;
                }
            }
        }
        return $contactId ?: null;
    }

    /**
     * @return mixed
     */
    public function getRetailP21CustomerId()
    {
        return $this->scopeConfig->getValue('p21/feeds/retail_customer_id');
    }

    /**
     * @return string
     */
    public function getSalesRepsUserGroupCode()
    {
        return $this->scopeConfig->getValue('p21/feeds/sales_reps_user_group_code');
    }

    /**
     * Centralized method to fetch a customer's P21 Sales Rep Id given a customer entity id
     * This is the customer's own P21 Sales Rep Id, not the sales rep that's been assigned to the customer
     *
     * @param int $customerId
     * @return string|null
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getCustomersOwnP21SalesRepId($customerId)
    {
        try {
            $customer = $this->customerRepository->getById($customerId);

            $customerCode = $customer->getCustomAttribute('erp_sales_rep_id');

            return (!is_null($customerCode) ? $customerCode->getValue() : null);
        } // no such customer was found, and therefore the code will be null, no need to do anything
        catch (NoSuchEntityException $e) {
        }

        return null;
    }

    /**
     * @param $customerId
     * @return bool
     */
    public function isPoNumberRequiredForCustomerId($customerId)
    {
        try {
            $customerCode = $this->getP21CustomerIdByMagentoCustomerId($customerId);

            if (!empty($customerCode)) {
                try {
                    return $this->api->getCustomerPoNumberFlag($customerCode);
                } catch (P21ApiException $e) {
                    // unable to fetch customer data with api call
                    $this->logger->error($e->getMessage());
                    return false;
                }
            }
            return false;
        } catch (LocalizedException $e) {
            return false;
        }
    }

    /**
     * @param $customer
     * @return string
     */
    public function getP21CustomerName($customer)
    {
        $customerName = "";
        try {
            $customerName = $this->api->getCustomerName($this->getP21CustomerId($customer));
        } catch (P21ApiException $e) {
            $this->logger->error($e->getMessage());
        }
        return (string)$customerName;
    }

    /**
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function isLoggedInCustomerASalesRep()
    {
        if (!$this->customerSession->isLoggedIn()) {
            return false;
        }

        $group = $this->groupRepository->getById($this->customerSession->getCustomerData()->getGroupId());

        return $group->getCode() === $this->getSalesRepsUserGroupCode();
    }

    /**
     * @param $customerId
     * @return bool
     */
    public function isTaxable($customerId)
    {
        try {
            $customerCode = $this->getP21CustomerIdByMagentoCustomerId($customerId);
            if (!empty($customerCode)) {
                return $this->api->getCustomerTaxableFlag($customerCode);
            }
        } catch (P21ApiException | LocalizedException $e) {
            $this->logger->error($e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }
        return false;
    }

    /**
     * @return bool
     */
    public function isLoggedInCustomerWholesaleCustomer()
    {
        // do the obvious if the customer is not logged in
        if (!$this->customerSession->isLoggedIn()) {
            return false;
        }

        $websiteId = $this->customerSession->getCustomerData()->getWebsiteId();

        return $websiteId === $this->multistoreHelper->getWholesaleWebsiteId();
    }

}
