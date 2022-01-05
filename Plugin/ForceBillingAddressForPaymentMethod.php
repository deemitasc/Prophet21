<?php
/**
 * Overrides Magento\Checkout\Model\PaymentInformationManagement
 */
namespace Ripen\Prophet21\Plugin;

use Ripen\SimpleApps\Model\Api;
use Ripen\Prophet21\Helper\DataHelper;
use Psr\Log\LoggerInterface;
use Magento\Quote\Api\Data\AddressInterfaceFactory;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Checkout\Model\PaymentInformationManagement;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Store\Model\StoreManagerInterface;  
use Ripen\Prophet21\Helper\Customer as CustomerHelper;

class ForceBillingAddressForPaymentMethod
{
    /**
     * @var LoggerInterface 
     */
    protected $logger;

    /**
     * @var Api
     */
    protected $api;

    /**
     * @var AddressInterfaceFactory;
     */
    protected $addressDataFactory;

    /**
     * @var CartRepositoryInterface;
     */
    protected $cartRepository;

    /**
     * @var DataHelper
     */
    private $helper;

    /**
     * @var StoreManagerInterface;  
     */
    protected $storeManager;

    /**
     * @var CustomerHelper;  
     */
    protected $customerHelper;

    /**
     * @param LoggerInterface $logger
     * @param Api $api
     * @param AddressInterfaceFactory $addressDataFactory
     * @param CartRepositoryInterface $cartRepoistory
     * @param StoreManagerInterface $storeManager
     * @param DataHelper $helper 
     * @param CustomerHelper $customerHelper 
     */
    public function __construct(
        LoggerInterface $logger,
        Api $api,
        AddressInterfaceFactory $addressDataFactory,
        CartRepositoryInterface $cartRepoistory,
        StoreManagerInterface $storeManager,  
        DataHelper $helper,
        CustomerHelper $customerHelper
    ){
        $this->logger = $logger;
        $this->api = $api;
        $this->addressDataFactory = $addressDataFactory;
        $this->cartRepository = $cartRepoistory;
        $this->storeManager = $storeManager;
        $this->helper = $helper;
        $this->customerHelper = $customerHelper;
    }

    /**
     * This method can used to force a customers p21 billing adderess to be set for any payment types we allow.
     * These payment types can be configured in the extension settings.
     * 
     * @param PaymentInformationManagement $subject
     * @param mixed $cartId         
     * @param PaymentInterface $paymentMethod,
     * @param AddressInterface $billingAddress = null
     * @return array
     */
    public function beforeSavePaymentInformation(
        PaymentInformationManagement $subject,
        $cartId,
        PaymentInterface $paymentMethod,
        AddressInterface $billingAddress = null
    ){
        // Get what methods we allow to force our p21 billing address for (EX: COD)
        $methodsString = $this->helper->getP21BillToPaymentMethods($this->storeManager->getStore()->getId());
        if(empty($methodsString)) {
            return [$cartId, $paymentMethod, $billingAddress];
        }

        $quote = $this->cartRepository->getActive($cartId);
        $customer = $quote->getCustomer();

        $customerId = $this->customerHelper->getP21CustomerIdByMagentoCustomerId($customer->getId());
        if(empty($customerId)) {
            return [$cartId, $paymentMethod, $billingAddress];
        }
       
        $billtos = $this->api->getCustomerBillTos($customerId);
        if(empty($billtos)) {
            return [$cartId, $paymentMethod, $billingAddress];
        }
        
        // Get the first billto address returned from p21, if the array is not empty.
        // Then iterate through our allowed payment methods, 
        // if our current payment method matches an allowed method we attach the p21 billing address to the quote.
        $billtos = $billtos[0];
        $paymentMethods = explode(',', $methodsString);
        if (in_array($paymentMethod->getMethod(), $paymentMethods)){
            $billingAddress = $this->addressDataFactory->create();
            $billingAddress->setFirstname($customer->getFirstname())
                ->setLastname($customer->getLastname())
                ->setCustomerId($customer->getId())
                ->setCompany($billtos['name'])
                ->setCountryId($billtos['country_id'])
                ->setRegionCode($billtos['region'])
                ->setCity($billtos['city'])
                ->setPostcode($billtos['postcode'])
                ->setStreet([$billtos['street1'], $billtos['street2']])
                ->setTelephone($billtos['telephone']);     
        }
        return [$cartId, $paymentMethod, $billingAddress];
    }
}
