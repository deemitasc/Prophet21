<?php
/**
 * Exports Orders to API
 */

namespace Ripen\Prophet21\Cron\Sales;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Ripen\Prophet21\Helper\Customer as CustomerHelper;
use Ripen\Prophet21\Exception\P21ApiException;
use Ripen\SimpleApps\Model\Api;

class ExportOrders
{
    const PROCESSOR_NAME = 'element';
    const SHIPPING_SKU = 'FREIGHT';
    const CC_PAYMENT_METHOD_CODE = 'ripen_vantivintegratedpayments';

    /**
     * @var Api
     */
    protected $api;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var \Magento\SalesRule\Model\ResourceModel\Rule\CollectionFactory
     */
    protected $salesRuleCollectionFactory;

    /**
     * @var \Magento\SalesRule\Api\CouponRepositoryInterface
     */
    protected $salesRuleCouponRepository;

    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Magento\Directory\Model\RegionFactory
     */
    protected $regionFactory;

    /**
     * @var \Magento\Customer\Model\CustomerFactory
     */
    protected $customerFactory;

    /**
     * @var \Ripen\Prophet21\Model\ShippingMethodMapper
     */
    protected $shippingMethodMapper;

    /**
     * @var \Ripen\Prophet21\Helper\WholesaleHelper
     */
    protected $wholesaleHelper;

    /**
     * @var \Ripen\Prophet21\Helper\Customer
     */
    protected $customerHelper;

    /**
     * @var \Ripen\Prophet21\Model\CcTypeMapper
     */
    protected $ccTypeMapper;

    public function __construct(
        Api $api,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\SalesRule\Model\ResourceModel\Rule\CollectionFactory $salesRuleCollectionFactory,
        \Magento\SalesRule\Api\CouponRepositoryInterface $salesRuleCouponRepository,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Ripen\Prophet21\Logger\Logger $logger,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Directory\Model\RegionFactory $regionFactory,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Ripen\Prophet21\Model\ShippingMethodMapper $shippingMethodMapper,
        \Ripen\Prophet21\Helper\WholesaleHelper $wholesaleHelper,
        \Ripen\Prophet21\Helper\Customer $customerHelper,
        \Ripen\Prophet21\Model\CcTypeMapper $ccTypeMapper
    ) {
        $this->api = $api;
        $this->regionFactory = $regionFactory;
        $this->orderRepository = $orderRepository;
        $this->salesRuleCollectionFactory = $salesRuleCollectionFactory;
        $this->salesRuleCouponRepository = $salesRuleCouponRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
        $this->customerFactory = $customerFactory;
        $this->shippingMethodMapper = $shippingMethodMapper;
        $this->wholesaleHelper = $wholesaleHelper;
        $this->customerHelper = $customerHelper;
        $this->ccTypeMapper = $ccTypeMapper;
    }

    public function execute()
    {
        $this->logger->info('Exporting orders...');

        if (!$this->scopeConfig->getValue('p21/feeds/enable_order_export_cron')) {
            $this->logger->info('Interrupted. Disabled in admin settings.');
            return;
        }

        /**
         * Get orders without assigned web_orders_uid and p21_order_no to make sure we are exporting only new orders.
         *
         * Also, check that remote_ip is not NULL. This ensures that order was placed directly on the website and not
         * created programmatically by a cron importing orders from p21.  However, if another sales channel is added
         * and if it routes directly into Magento, such as selling through Amazon, then this solution would fail
         * (remote_ip may also be NULL for such orders)
         *
         * Also, check that state is processing. Any other state means it's either not a new order (though this should
         * never happen) or is an order that potentially shouldn't sync to P21 (fraud, payment pending or on hold).
         */
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('web_orders_uid', 0)
            ->addFilter('p21_order_no', 0)
            ->addFilter('remote_ip', true, 'notnull')
            ->addFilter('state', [Order::STATE_NEW, Order::STATE_PROCESSING], 'in')
            ->create();

        $orders = $this->orderRepository->getList($searchCriteria);

        if ($orders->getTotalCount() > 0) {
            /** @var \Magento\Sales\Api\Data\OrderInterface $order */
            foreach ($orders as $order) {
                try {
                    // generate exportData array for API
                    $exportData = $this->getExportData($order);

                    // send exportData to API and save the returned Web Order Uid
                    $webUid = $this->api->postWebOrder($exportData);
                    if ($webUid) {
                        $this->logger->info("Exported order [{$order->getIncrementId()}] - returned Web Ref Id [{$webUid}]");
                    } else {
                        $this->logger->info("Exporting order [{$order->getIncrementId()}] failed - checking for prior export");

                        /**
                         * Double check API in case the order has actually made it to the API, but simply did not return
                         * proper webUid due to timeout
                         */
                        $webOrderResponse = $this->api->getWebOrdersByMagentoOrderId($order->getIncrementId());
                        $webUidArray = $this->api->parseWebOrderUidsAsArray($webOrderResponse);
                        $webUid = $webUidArray[0] ?? null;

                        if ($webUid) {
                            $this->logger->info("Saving prior Web Ref Id [{$webUid}] for order [{$order->getIncrementId()}]");
                        } else {
                            throw new \Exception('Web Ref Id was not returned.');
                        }
                    }

                    $order->setWebOrdersUid((int) $webUid);
                    $order->save();
                } catch (\Exception $e) {
                    $this->logger->critical("Error exporting order [{$order->getIncrementId()}]: {$e->getMessage()}");
                }
            }
        }
    }

    /**
     * Generates the necessary export data array for a given order
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Ripen\Prophet21\Exception\CustomerCodeEmptyException
     */
    public function getExportData(\Magento\Sales\Api\Data\OrderInterface $order)
    {
        $orderItems = $order->getItems();
        // TODO: Load with customer registry if possible, or if that doesn't work, note why here.
        /** @var \Magento\Customer\Model\Customer $customer */
        $customer = $this->customerFactory->create()->load($order->getCustomerId());

        $lines = [];
        foreach ($orderItems as $orderItem) {
            if ($orderItem->getParentItem()) {
                continue;
            }

            $orderItemOptions = $orderItem->getProductOptions();
            if (!empty($orderItemOptions['source'])) {
                // Use inventory source selected for order item
                $inventorySource = $orderItemOptions['source'];
            } elseif ($customer->getData('default_shipping_warehouse_id')) {
                // Use inventory source set for customer as default
                $inventorySource = $customer->getData('default_shipping_warehouse_id');
            } else {
                // Use inventory source set as global default
                $inventorySource = $this->scopeConfig->getValue('p21/feeds/default_inventory_source_id');
            }

            $note = '';
            if (!empty($orderItemOptions['options'])) {
                foreach($orderItemOptions['options'] as $orderItemOption){
                    $note .= $orderItemOption['label'].": ".$orderItemOption['value']."\n";
                }
            }

            $lines[] = [
                'item_id' => $orderItem->getSku(),
                'qty' => $orderItem->getQtyOrdered(),
                'uom' => $orderItem->getData(\Ripen\Prophet21\Model\CustomerUOM::UOM_ITEM_KEY_NAME) ?: $orderItem->getProduct()->getData('p21_default_selling_unit'),
                'source_loc_id' => $inventorySource,
                'unit_price' => $orderItem->getPrice(),
                'manual_price_override' => 'Y',
            ];

            if(!empty($note)) {
                $lines['note'] = $note;
            }
        }

        // if there are any discounts, add them as line items with its rule_sku as item_id
        if ($order->getCouponCode()) {
            // look for salesrule_coupon with the associated coupon code
            $searchCriteria = $this->searchCriteriaBuilder->addFilter('code', $order->getCouponCode())->create();

            try {
                // if we have found a matching salesrule_coupon
                $coupons = $this->salesRuleCouponRepository->getList($searchCriteria)->getItems();
                $ruleIds = [];

                /**
                 * Even though ->getList() does not explicitly throw NoSuchEntityException,
                 * we still want to log this in case there was no coupon found with the coupon code used in the order
                 */
                if (count($coupons) == 0) {
                    $this->logger->error('Ripen_Prophet21 ' . __CLASS__ . ' salesRuleCouponRepository->getList() Failure - Message :: No coupon was found with code of "' . $order->getCouponCode() . '"');
                }

                /**
                 * In theory, there should only ever be 1 coupon found this way since we are filtering by coupon_code,
                 * but we are looping through the results due to the results being returned as collection
                 */
                foreach ($coupons as $coupon) {
                    $ruleIds[] = $coupon->getRuleId();
                }

                foreach ($ruleIds as $ruleId) {
                    // find the corresponding salesrule via rule_id in order to fetch the associated rule_sku
                    $rule = $this->salesRuleCollectionFactory->create()
                        ->addFieldToFilter('rule_id', $ruleId)
                        ->getFirstItem();

                    $ruleSku = $rule->getRuleSku();
                    if (! empty($ruleSku)) {
                        $lines[] = [
                            'item_id' => $ruleSku,
                            'qty' => 1,
                            'uom' => 'EA',
                            /**
                             * Magento doesn't track discounts for individual coupon codes on order item level.
                             * For example, if two codes were applied for the same order item, we only know total
                             * discount based on both codes. For that reason, we can't calculate total discount
                             * per specific coupon code (across all order items). So for each code we are recording
                             * total order discount along with a text note.
                             */
                            'unit_price' => $order->getDiscountAmount(),
                            'manual_price_override' => 'Y',
                            'note' => 'Unit price reflects a total discount for all applied coupon codes'
                        ];
                    }
                }
            } catch (LocalizedException $e) {
                $this->logger->error('Ripen_Prophet21 ' . __CLASS__ . ' salesRuleCouponRepository->getList() Failure - Message :: ' . $e->getLogMessage());
            }
        }

        // Pass shipping fee as individual line item
        if ($order->getShippingAmount() > 0) {
            $lines[] = [
                'item_id' => self::SHIPPING_SKU,
                'qty' => 1,
                'uom' => 'EA',
                'unit_price' => $order->getShippingAmount(),
                'manual_price_override' => 'Y'
            ];
        }

        $orderIsWholesale = false;

        // If this is a wholesale customer, check for p21 customer code there
        if ($this->wholesaleHelper->isCustomerWholesale($customer)){
            $orderIsWholesale = true;
            $p21CustomerIdOnOrder = $order->getData(CustomerHelper::P21_CUSTOMER_ID_FIELD);

            // If the customer ID is already associated to the order, use that
            if (! empty($p21CustomerIdOnOrder)) {
                $p21CustomerCode = $p21CustomerIdOnOrder;
            }
            else {
                $p21CustomerCode = $this->customerHelper->getP21CustomerId($customer);
            }

            // Handle in case the actual code value is empty or null
            if (empty($p21CustomerCode)) {
                throw new \Ripen\Prophet21\Exception\CustomerCodeEmptyException(__('Customer code is empty or null for customer ID: ' . $customer->getId()));
            }
        } else {
            $p21CustomerCode = (int) $this->customerHelper->getRetailP21CustomerId();
        }

        $payment = $order->getPayment();

        /** @var \Magento\Sales\Model\Order\Address $shippingAddress */
        $shippingAddress = $order->getShippingAddress();
        $shippingState = $this->regionFactory->create()->loadByName($shippingAddress->getData('region'), $shippingAddress->getData('country_id'));
        $shippingAddressPieces = explode(PHP_EOL, $shippingAddress->getData('street'));
        $shipToData = $this->getShipToData($order, $p21CustomerCode);

        if ($orderIsWholesale) {
            $shipToName = $shippingAddress->getData('company') ?: $shippingAddress->getName();
            $orderApproved = 'Y';   // wholesale orders are auto-approved
        } else {
            $shipToName = $shippingAddress->getName();
            $autoApproveForGuests =  $this->scopeConfig->getValue('p21/integration/auto_approve_orders_for_guests');
            $orderApproved = $autoApproveForGuests ? 'Y' : 'N';
        }

        $paymentType = $payment->getMethod();
        $useCcType =  $this->scopeConfig->getValue('p21/feeds/use_credit_card_codes_for_payment_type');
        if($paymentType == self::CC_PAYMENT_METHOD_CODE && $useCcType) {
            $paymentType = $this->ccTypeMapper->getP21PaymentTypeCode($payment->getCcType());
        }

        $exportData = [
            'header' => [
                'magento_order_no' => $order->getIncrementId(),
                'customer_id' => $p21CustomerCode,
                'contact_id' => $this->customerHelper->getP21ContactId($customer),
                'company_id' => $this->getCompanyId(),
                'quote' => 'N',
                'import_as_quote' => 'N',
                'customer_po_number' => !empty($order->getData('po_number')) ? $order->getData('po_number') : $payment->getPoNumber(),
                'ship_to_id' => $shipToData['address_id'],
                'ship_to_name' => $shipToName,
                'ship_to_address1' => $shippingAddressPieces[0],
                'ship_to_address2' => !empty($shippingAddressPieces[1]) ? $shippingAddressPieces[1] : '',
                'ship_to_address3' => !empty($shippingAddressPieces[2]) ? $shippingAddressPieces[2] : '',
                'ship_to_city' => $shippingAddress->getData('city'),
                'ship_to_state' => $shippingState->getCode(),
                'ship_to_country' => $shippingAddress->getData('country_id'),
                'ship_to_postal_code' => $shippingAddress->getData('postcode'),
                'ship_to_companyname' => $shippingAddress->getData('company'),
                'ship_to_phone' => $shippingAddress->getData('telephone'),
                'ship_to_email' => $shippingAddress->getData('email'),
                'payment_type' => $paymentType,
                'payment_method_name' => $payment->getAdditionalInformation('method_title'),
                'approved' => $orderApproved,
                'carrier_id' => $this->shippingMethodMapper->getP21CarrierId($order->getShippingMethod()),
                'packing_basis' => $order->getData('ship_entire_only')
                    ? 'Order Complete'
                    : $shipToData['default_packing_basis'],
                'cart_tax' => $order->getTaxAmount(),
                'shipping_estimate' => $order->getShippingAmount()
            ],
            'lines' => $lines
        ];

        if($order->getComments()){
            $exportData['notes'] = [
                'note' => $order->getComments(),
                'note_areas' => [
                    'Order Entry',
                    'Print Pick Tickets'
                ]
            ];
        }

        // handle CC payment order
        if ($payment->getCcLast4() && $payment->getMethod() == self::CC_PAYMENT_METHOD_CODE) {
            $billingAddress = $order->getBillingAddress();
            $billingAddressPieces = explode(PHP_EOL, $billingAddress->getData('street'));

            $billingState = $this->regionFactory->create()->loadByName($billingAddress->getData('region'), $billingAddress->getData('country_id'));

            // fetch additional information stored by Ripen\VantivIntegratedPayments\Model\Cc::authorize()
            $additionalInformation = $payment->getAdditionalInformation();
            $exportData['payments'] = [
                'first_name' => $billingAddress->getData('firstname'),
                'last_name' => $billingAddress->getData('lastname'),
                'street_address1' => $billingAddressPieces[0],
                'street_address2' => !empty($billingAddressPieces[1]) ? $billingAddressPieces[1] : '',
                'street_address3' => !empty($billingAddressPieces[2]) ? $billingAddressPieces[2] : '',
                'city' => $billingAddress->getData('city'),
                'state' => $billingState->getCode(),
                'zip_code' => $billingAddress->getData('postcode'),
                'country' => $billingAddress->getData('country_id'),
                'processor' => self::PROCESSOR_NAME,
                'TransactionSetupID' => $payment->getLastTransId(),
                'PaymentAccountID' => isset($additionalInformation['PaymentAccountID']) ? $additionalInformation['PaymentAccountID'] : null,
                'ExpressResponseCode' => isset($additionalInformation['ExpressResponseCode']) ? $additionalInformation['ExpressResponseCode'] : null,
                'ExpressResponseMessage' => isset($additionalInformation['ExpressResponseMessage']) ? $additionalInformation['ExpressResponseMessage'] : null,
                'LastFour' => $payment->getCcLast4(),
                'ValidationCode' => null,   // ValidationCode is not supplied by Vantiv API's CreditCardAuthorization class; it is supplied by the Hosted Payment Page that we aren't using
                'ExpirationMonth' => $payment->getCcExpMonth(),
                'ExpirationYear' => substr($payment->getCcExpYear(), -2),
                'PaymentBrand' => isset($additionalInformation['CardBrand']) ? $additionalInformation['CardBrand'] : $payment->getCcType(),
                'CardLogo' => isset($additionalInformation['CardLogo']) ? $additionalInformation['CardLogo'] : $payment->getCcType(),
            ];
        }

        return $exportData;
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @param int $customerCode
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function getShipToData(
        \Magento\Sales\Api\Data\OrderInterface $order,
        int $customerCode
    ) {
        try {
            $shipTos = $this->api->getCustomerShipTos($customerCode);
        } catch (P21ApiException $e) {
            throw new LocalizedException(__("Failed to retrieve customer ship-tos for customer [{$customerCode}]"));
        }

        $explicitShipToId = $order->getShippingAddress()->getData('prophet_21_id');
        if ($explicitShipToId) {
            foreach ($shipTos as $shipToData) {
                if ($shipToData['address_id'] == $explicitShipToId) {
                    return $shipToData;
                }
            }
        }

        // If no explicitly set ship-to data can be located, fall back to first valid ship-to for that customer.
        // We're making explicit what SimpleApps would do by default in the case of a null ship-to ID:
        // https://basecamp.com/2805226/projects/16452113/todos/407284616#comment_739933516
        return $shipTos[0] ?? null;
    }

    /**
     * @return mixed
     */
    protected function getCompanyId()
    {
        return $this->scopeConfig->getValue('p21/feeds/retail_company_id');
    }
}
