<?php
/**
 * Plugin to set P21 customer ID after customer creation via Greenwing Technology module, if it was supplied by
 * the POST data. Does nothing if the module is not present.
 */

namespace Ripen\Prophet21\Plugin\Greenwing;

use Greenwing\Technology\Model\CategoryLinkManagement;
use Magento\Framework\App\PlainTextRequestInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Ripen\Prophet21\Helper\Customer;
use Psr\Log\LoggerInterface;

class SetCustomerP21IdOnCustomer
{
    const P21_CUSTOMER_ID_POST_FIELD = 'ERPCustomerID';

    /**
     * @var PlainTextRequestInterface
     */
    protected $plainTextRequest;

    /**
     * @var CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param PlainTextRequestInterface $plainTextRequest
     * @param CustomerRepositoryInterface $customerRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        PlainTextRequestInterface $plainTextRequest,
        CustomerRepositoryInterface $customerRepository,
        LoggerInterface $logger
    ) {
        $this->plainTextRequest = $plainTextRequest;
        $this->customerRepository = $customerRepository;
        $this->logger = $logger;
    }

    /**
     * @param CategoryLinkManagement $subject
     * @param $result
     * @param $pfname
     * @param $plname
     * @param $pemail
     * @param $customergroup
     * @param $ppassword
     * @return mixed
     */
    public function afterLogin(
        CategoryLinkManagement $subject,
        $result,
        $pfname,
        $plname,
        $pemail,
        $customergroup,
        $ppassword
    ) {
        $jsonPostData = $this->plainTextRequest->getContent();
        $postData = json_decode($jsonPostData);

        if (property_exists($postData, 'GWTSSO') && property_exists($postData->GWTSSO, self::P21_CUSTOMER_ID_POST_FIELD)) {
            $p21CustomerId = $postData->GWTSSO->{self::P21_CUSTOMER_ID_POST_FIELD};

            if (!empty($p21CustomerId)) {
                try {
                    $customer = $this->customerRepository->get($pemail);

                    // Do not overwrite any existing P21 Customer ID
                    if (empty($customer->getCustomAttribute(Customer::P21_CUSTOMER_ID_FIELD))) {
                        $customer->setCustomAttribute(Customer::P21_CUSTOMER_ID_FIELD, $p21CustomerId);
                        $this->customerRepository->save($customer);
                    }
                } catch (\Exception $e) {
                    $this->logger->error($e->getMessage());
                }
            }
        }

        return $result;
    }
}
