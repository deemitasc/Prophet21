<?php

namespace Ripen\Prophet21\Model;

use Ripen\Prophet21\Helper\Customer as CustomerHelper;

/**
 * Class OrdersFeed
 * @package Ripen\Prophet21\Model
 */
abstract class AbstractIncomingOrdersFeed
{
    /**
     * API call to get orders always limits the response with 10 items. "limit" parameter must be passed to
     * override this limit. We set this parameter to a random high number to get all orders for a specific customer.
     */
    const ORDERS_IMPORT_LIMIT = 1000000; // Limit number of imported orders
    const ORDER_SYNC_BATCH_SIZE = 100;

    /**
     * @var \Ripen\SimpleApps\Model\Api
     */
    protected $api;

    /**
     * @var \Ripen\Prophet21\Model\ShippingTrackingCarrierIdentifier
     */
    protected $shippingTrackingCarrierIdentifier;


    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $orderRepository;

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
     * @var \Magento\Sales\Model\Service\InvoiceService
     */
    protected $invoiceService;

    /**
     * @var \Magento\Framework\DB\Transaction
     */
    protected $transaction;

    /**
     * @var \Magento\Sales\Model\Convert\Order
     */
    protected $convertOrder;

    /**
     * @var \Magento\Shipping\Model\ShipmentNotifier
     */
    protected $shipmentNotifier;

    /**
     * @var \Magento\Sales\Model\Order\Shipment\TrackFactory
     */
    protected $trackFactory;

    /**
     * @var \Ripen\Prophet21\Model\PickListRepository
     */
    protected $pickListRepository;

    /**
     * @var \Magento\Catalog\Model\Product
     */
    protected $product;

    /**
     * @var \Magento\Catalog\Helper\Product
     */
    protected $productHelper;

    /**
     * @var \Ripen\Prophet21\Helper\Customer
     */
    protected $customerHelper;

    /**
     * @var \Ripen\Prophet21\Helper\MultistoreHelper
     */
    protected $multistoreHelper;

    /**
     * @var \Magento\Sales\Api\OrderManagementInterface
     */
    protected $orderManagement;

    /**
     * @var \Magento\Customer\Model\CustomerFactory
     */
    protected $customerCollectionFactory;

    /**
     * @var \Magento\Customer\Api\CustomerRepositoryInterface
     */
    protected $customerRepositoryInterface;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Catalog\Model\ProductFactory
     */
    protected $productFactory;

    /**
     * @var \Magento\Quote\Model\QuoteFactory
     */
    protected $quote;

    /**
     * @var \Magento\Quote\Model\QuoteManagement
     */
    protected $quoteManagement;

    /**
     * @var \Magento\Quote\Api\Data\CartItemInterfaceFactory
     */
    protected $cartItemFactory;

    /**
     * @var \Magento\Customer\Model\CustomerFactory
     */
    protected $customerFactory;

    /**
     * @var \Magento\Store\Model\App\Emulation
     */
    protected $emulation;

    /**
     * @var \Magento\InventoryApi\Api\SourceRepositoryInterface
     */
    protected $sourceRepository;

    /**
     * @var \Magento\Framework\Api\FilterBuilder
     */
    protected $filterBuilder;

    /**
     * @var \Ripen\Prophet21\Model\InvoiceRepository
     */
    protected $invoiceRepository;

    /**
     * @var \Ripen\Prophet21\Model\ShippingMethodMapper
     */
    protected $shippingMethodMapper;

    /**
     * @var \Magento\Directory\Model\CountryFactory
     */
    protected $countryFactory;

    /**
     * @var \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface
     */
    protected $transactionBuilder;

    /**
     * @var \Magento\Framework\Api\SortOrderBuilder
     */
    protected $sortOrderBuilder;

    public function __construct(
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Framework\DB\Transaction $transaction,
        \Magento\Sales\Model\Convert\Order $convertOrder,
        \Magento\Shipping\Model\ShipmentNotifier $shipmentNotifier,
        \Magento\Sales\Model\Order\Shipment\TrackFactory $trackFactory,
        \Magento\Catalog\Model\Product $product,
        \Magento\Catalog\Helper\Product $productHelper,
        \Magento\Sales\Api\OrderManagementInterface $orderManagement,
        \Magento\Customer\Model\ResourceModel\Customer\CollectionFactory $customerCollectionFactory,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepositoryInterface,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Quote\Model\QuoteFactory $quote,
        \Magento\Quote\Model\QuoteManagement $quoteManagement,
        \Magento\Quote\Api\Data\CartItemInterfaceFactory $cartItemFactory,
        \Ripen\SimpleApps\Model\Api $api,
        \Ripen\Prophet21\Logger\Logger $logger,
        \Ripen\Prophet21\Model\PickListRepository $pickListRepository,
        \Ripen\Prophet21\Model\InvoiceRepository $invoiceRepository,
        \Ripen\Prophet21\Model\ShippingMethodMapper $shippingMethodMapper,
        \Ripen\Prophet21\Helper\Customer $customerHelper,
        \Ripen\Prophet21\Helper\MultistoreHelper $multistoreHelper,
        \Ripen\Prophet21\Model\ShippingTrackingCarrierIdentifier $shippingTrackingCarrierIdentifier,
        \Magento\Store\Model\App\Emulation $emulation,
        \Magento\InventoryApi\Api\SourceRepositoryInterface $sourceRepository,
        \Magento\Framework\Api\FilterBuilder $filterBuilder,
        \Magento\Directory\Model\CountryFactory $countryFactory,
        \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder,
        \Magento\Framework\Api\SortOrderBuilder $sortOrderBuilder,
        \Magento\InventorySalesApi\Model\StockByWebsiteIdResolverInterface $stockByWebsiteIdResolver,
        \Magento\InventorySalesApi\Api\IsProductSalableForRequestedQtyInterface $isProductSalableForRequestedQty

    ) {
        $this->storeManager = $storeManager;
        $this->api = $api;
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
        $this->invoiceService = $invoiceService;
        $this->transaction = $transaction;
        $this->convertOrder = $convertOrder;
        $this->shipmentNotifier = $shipmentNotifier;
        $this->trackFactory = $trackFactory;
        $this->pickListRepository = $pickListRepository;
        $this->invoiceRepository = $invoiceRepository;
        $this->shippingMethodMapper = $shippingMethodMapper;
        $this->product = $product;
        $this->productHelper = $productHelper;
        $this->customerHelper = $customerHelper;
        $this->multistoreHelper = $multistoreHelper;
        $this->orderManagement = $orderManagement;
        $this->customerCollectionFactory = $customerCollectionFactory;
        $this->customerRepositoryInterface = $customerRepositoryInterface;
        $this->customerFactory = $customerFactory;
        $this->productFactory = $productFactory;
        $this->quote = $quote;
        $this->quoteManagement = $quoteManagement;
        $this->cartItemFactory = $cartItemFactory;
        $this->emulation = $emulation;
        $this->sourceRepository = $sourceRepository;
        $this->filterBuilder = $filterBuilder;
        $this->countryFactory = $countryFactory;
        $this->transactionBuilder = $transactionBuilder;
        $this->sortOrderBuilder = $sortOrderBuilder;
        $this->stockByWebsiteIdResolver = $stockByWebsiteIdResolver;
        $this->isProductSalableForRequestedQty = $isProductSalableForRequestedQty;
        $this->shippingTrackingCarrierIdentifier = $shippingTrackingCarrierIdentifier;

    }

    public function splitName($name)
    {
        $nameParts = explode(' ', trim(str_replace('#', '', $name)), 2);
        $firstName = $nameParts[0];
        $lastName = trim($nameParts[1] ?? $firstName);
        return [$firstName, $lastName];
    }

    protected function createNewOrder($p21OrderNumber, $p21CustomerId)
    {
        $params['customer_id'] = $p21CustomerId;

        try{
            $p21Order = $this->api->getP21Order($p21OrderNumber, $params);
            $p21FirstInvoice = $this->getFirstP21Invoice($p21OrderNumber, $p21CustomerId);

        } catch (\Exception $e) {
            $this->logger->error("Failed to import order_no [{$p21OrderNumber}] for customer [{$p21CustomerId}] - " . $e->getMessage());
            return;
        }
        if (!$p21FirstInvoice) {
            $this->logger->error("Skip importing. Order [{$p21OrderNumber}] - missing P21 invoice");
            return;
        }

        $poNumber = !empty($p21FirstInvoice['po_no']) ? $p21FirstInvoice['po_no'] : '-';

        // Check if this works for all clients (Ritz?)
        if ($p21Order['customer_id'] == $this->customerHelper->getRetailP21CustomerId()) {
            $websiteId = $this->multistoreHelper->getRetailWebsiteId();
            $storeCode = $this->multistoreHelper->getRetailStoreCode();
            $storeId = $this->multistoreHelper->getRetailStoreId();
            $paymentMethod = "ripen_vantivintegratedpayments";
        } else {
            $websiteId = $this->multistoreHelper->getWholesaleWebsiteId();
            $storeCode = $this->multistoreHelper->getWholesaleStoreCode();
            $storeId = $this->multistoreHelper->getWholesaleStoreId();

            // TODO: check if it can be hardcoded or needs a remapped value from api response; update README if changed
            $paymentMethod = "invoicepayment";
        }

        if ($p21Order) {
            if (is_numeric($p21Order['ship2_country']) || is_numeric($p21FirstInvoice['bill2_country'])) {
                $this->logger->error("Skip importing. Order [{$p21OrderNumber}] - invalid country code (numeric value)");
                return;
            }

            $p21CustomerName = $this->splitName($p21Order['ship2_name']);
            $p21CustomerBillingName = $this->splitName($p21FirstInvoice['bill2_name']);

            $p21OrderLineItems = $this->api->getOrderLineItems($p21OrderNumber, $params);
            $items = [];
            foreach ($p21OrderLineItems as $p21OrderLineItem) {
                $sku = $this->api->parseLineItemSku($p21OrderLineItem);
                $productId = $this->product->getIdBySku($sku);
                if ($productId) {
                    $taxAmount = $this->api->parseOrderLineItemTaxAmount($p21OrderLineItem);
                    $lineItemPrice = $this->api->parseLineItemPrice($p21OrderLineItem);
                    $taxPercent = $lineItemPrice ? round($taxAmount * 100 / $lineItemPrice) : 0;

                    $items[] = [
                        'sku' => $sku,
                        'product_id' => $productId,
                        'qty'=> $this->api->parseLineItemQty($p21OrderLineItem),
                        'qty_canceled' => $this->api->parseLineItemQtyCanceled($p21OrderLineItem),
                        'price' => $this->api->parseLineItemPrice($p21OrderLineItem),
                        'tax_amount' => $taxAmount,
                        'tax_percent'=> $taxPercent,
                        'extended_price' => $this->api->parseLineItemExtendedPrice($p21OrderLineItem),
                        'p21_unique_id' => $this->api->parseOrderLineItemUniqueid($p21OrderLineItem),
                        'uom' => $this->api->parseUom($p21OrderLineItem),
                    ];
                } else {
                    $this->logger->error("Failed to import order_no [{$p21OrderNumber}] - product [{$sku}] is missing");
                    return;
                }
            }

            $p21ShippingStreet = (!$p21Order['ship2_add1']) ? '' : [$p21Order['ship2_add1'], $p21Order['ship2_add2']];
            $p21BillingStreet = (!$p21FirstInvoice['bill2_address1']) ? '' : [$p21FirstInvoice['bill2_address1'], $p21FirstInvoice['bill2_address2']];
            $p21ShippingCountryId = $this->formatCountryId($p21Order['ship2_country']);
            $p21BillingCountryId = $this->formatCountryId($p21FirstInvoice['bill2_country']);

            $shipPhone = $p21Order['ship_to_phone'];
            $billPhone = $p21FirstInvoice['ship_to_phone'] ?? $shipPhone;

            if (!$p21ShippingCountryId) {
                $this->logger->error("Failed to import order_no [{$p21OrderNumber}] - invalid shipping country [{$p21Order['ship2_country']}]");
                return;
            }
            if (!$p21BillingCountryId) {
                $this->logger->error("Failed to import order_no [{$p21OrderNumber}] - invalid billing country  [{$p21FirstInvoice['bill2_country']}]");
                return;
            }
            if (!$billPhone) {
                $this->logger->error("Failed to import order_no [{$p21OrderNumber}] - invalid billing phone");
                return;
            }
            if (!$p21BillingStreet) {
                $this->logger->error("Failed to import order_no [{$p21OrderNumber}] - invalid billing street");
                return;
            }
            if (empty($p21CustomerName[0])) {
                $this->logger->error("Failed to import order_no [{$p21OrderNumber}] - invalid customer first name");
                return;
            }

            try{
                $shippingMethod = $this->shippingMethodMapper->getMagentoMethodCode($p21Order['carrier_id']);
                $shippingCarrierName = $this->getP21CarrierName($p21Order['carrier_id']);
            } catch (\Exception $e) {
                $this->logger->error("Failed to import order_no [{$p21OrderNumber}] - " . $e->getMessage());
                return;
            }

            $p21OrderData = [
                'currency_id' => 'USD',
                'email' => $p21Order['ship2_email_address'],
                'customer_code' => $p21CustomerId,
                'p21_order_no' => $p21Order['order_no'],
                'web_orders_uid' => $p21Order['web_reference_no'],
                'shipping_address' => [
                    'firstname' => $p21CustomerName[0],
                    'lastname' => $p21CustomerName[1] ?: $p21CustomerName[0],
                    'street' => $p21ShippingStreet,
                    'city' => $p21Order['ship2_city'],
                    'country_id' => $p21ShippingCountryId,
                    'region' => $p21Order['ship2_state'],
                    'postcode' => $p21Order['ship2_zip'],
                    'telephone' => $shipPhone,
                    'save_in_address_book' => 0
                ],
                'billing_address' => [
                    'firstname' => $p21CustomerBillingName[0],
                    'lastname' => $p21CustomerBillingName[1] ?: $p21CustomerBillingName[0],
                    'street' => $p21BillingStreet,
                    'city' => $p21FirstInvoice['bill2_city'],
                    'country_id' => $p21BillingCountryId,
                    'region' => $p21FirstInvoice['bill2_state'],
                    'postcode' => $p21FirstInvoice['bill2_postal_code'],
                    'telephone' => $billPhone,
                    'save_in_address_book' => 0
                ],
                'items' => $items,
                'payment_method' => $paymentMethod,
                'po_number' => $poNumber,
                'shipping_method' => $shippingMethod ?: $this->getDefaultShippingMethodCode(),
                'shipping_description' => $shippingCarrierName ?: '-',
                'website_id' => $websiteId,
                'store_code' => $storeCode,
                'store_id' => $storeId,
                'order_date' => $p21Order['order_date']
            ];

            $result = $this->createMageOrder($p21OrderData);

            if (is_array($result)) {
                $msg = is_array($result['message']) ? implode(', ', $result['message']) : $result['message'];
                $this->logger->error("Failed to import. Order [{$p21OrderNumber}] - " . $msg);
                return;
            } else {
                $this->logger->info("Order [{$p21OrderNumber}] successfully created");
                return $result;
            }
        }
    }

    protected function getFirstP21Invoice($p21OrderNumber, $p21CustomerId)
    {
        $p21Invoice = false;
        $params['customer_id'] = $p21CustomerId;
        $p21PickTicketStubs = $this->api->getPickTicketStubs($p21OrderNumber, $params);
        if ($p21PickTicketStubs && !empty($p21PickTicketStubs[0]['invoice_no'])) {
            $p21InvoiceNumber = $p21PickTicketStubs[0]['invoice_no'];
            $p21Invoice = $this->api->getInvoice($p21InvoiceNumber, $params);
        }

        return $p21Invoice;
    }

    /**
     *
     * Creates a new invoice
     *
     * @param \Magento\Sales\Model\Order $order
     * @param $invoiceItems
     * @param $p21InvoiceDetails
     * @param $p21PickTicketNumber
     */
    protected function createInvoice($order, $invoiceItems, $p21InvoiceDetails, $p21PickTicketNumber)
    {
        $p21InvoiceNumber = $this->api->parseInvoiceNo($p21InvoiceDetails);

        if (!$order->canInvoice()) {
            $this->logger->error("Order [{$this->getOrderIncrementId($order)}] - invoice [{$p21InvoiceNumber}] cannot be created");
            return;
        }

        if (!$this->api->parseInvoiceAmountPaid($p21InvoiceDetails)) {
            $this->logger->error("Order [{$this->getOrderIncrementId($order)}] - skip creating invoice [{$p21InvoiceNumber}] - not paid");
            return;
        }

        try {

            $p21InvoiceFreight = $this->api->parseInvoiceFreight($p21InvoiceDetails);
            $p21InvoiceGrandTotal = $this->api->parseInvoiceSubtotal($p21InvoiceDetails);
            $p21InvoiceSubtotal = $p21InvoiceGrandTotal - $p21InvoiceFreight;

            $invoice = $this->invoiceService->prepareInvoice($order, $invoiceItems);
            $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::NOT_CAPTURE);
            $invoice->setShippingAmount($p21InvoiceFreight);
            $invoice->setSubtotal($p21InvoiceSubtotal);
            $invoice->setBaseSubtotal($p21InvoiceSubtotal);
            $invoice->setSubtotalInclTax($p21InvoiceSubtotal);
            $invoice->setBaseSubtotalInclTax($p21InvoiceSubtotal);
            $invoice->setShippingAmount($p21InvoiceFreight);
            $invoice->setBaseShippingAmount($p21InvoiceFreight);
            $invoice->setGrandTotal($p21InvoiceGrandTotal);
            $invoice->setBaseGrandTotal($p21InvoiceGrandTotal);

            $invoice->register();
            $invoice->save();

            /**
             * TODO: Add logic to track partial payments. Possibly using Transactions?
             */
            //$invoiceAmountPaid = $this->api->parseInvoiceAmountPaid($invoiceDetails);

            $transactionSave = $this->transaction
                ->addObject($invoice)
                ->addObject($invoice->getOrder());

            $transactionSave->save();

            $this->invoiceRepository->get($p21InvoiceNumber)->markAsProcessed();

            $this->logger->info("Order [{$this->getOrderIncrementId($order)}] - invoice [p21: {$p21InvoiceNumber}] created for pick ticket [{$p21PickTicketNumber}]");
        } catch (\Exception $e) {
            $this->logger->error('Ripen_Prophet21 - ' . __CLASS__ . ' - ' . __METHOD__ . ' - Line ' . __LINE__ . ' - Message : ' . $e->getMessage());
        }
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @throws \Ripen\Prophet21\Exception\P21ApiException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function processOrder($order)
    {
        $params = [];
        $orderDataChanged = false;

        $p21OrderNo = $order->getData('p21_order_no');
        // TODO: Load with customer registry if possible, or if that doesn't work, note why here.
        /** @var \Magento\Customer\Model\Customer $customer */
        $customer = $this->customerFactory->create()->load($order->getCustomerId());

        // If the P21 customer ID is already associated to the order, use that
        $p21CustomerIdOnOrder = $order->getData(CustomerHelper::P21_CUSTOMER_ID_FIELD);
        if (! empty($p21CustomerIdOnOrder)) {
            $params['customer_id'] = $p21CustomerIdOnOrder;
        }
        // Find the P21 customer ID associated to the customer
        else {
            if ($customer->getId() && $order->getStoreId() == $this->multistoreHelper->getWholesaleStoreId()) {
                if ($customer->getData(CustomerHelper::P21_CUSTOMER_ID_FIELD)) {
                    $params['customer_id'] = $customer->getData(CustomerHelper::P21_CUSTOMER_ID_FIELD);
                } else {
                    $this->logger->error("Order [{$this->getOrderIncrementId($order)}] - wholesale customer doesn't have valid P21 customer code");
                    return;
                }
            } else {
                $params['customer_id'] = $this->customerHelper->getRetailP21CustomerId();
            }
        }

        $p21Order = $this->api->getP21Order($p21OrderNo, $params);

        // Cancel order if it was cancelled in P21
        if ($this->api->parseP21OrderCancelFlag($p21Order)) {
            $this->orderManagement->cancel($order->getId());
            $this->logger->info("Order [{$this->getOrderIncrementId($order)}] - order cancelled");
            return;
        }

        $p21Carriers = $this->api->getCarriers($params);
        $shipments = [];
        $p21OrderShippingAmount = 0;

        $totalOrderTaxBasedOnP21Invoices = 0;

        // Get P21 shipments (aka picktickets)
        $p21PickTicketStubs = $this->api->getPickTicketStubs($p21OrderNo, $params);
        if (!$p21PickTicketStubs) {
            $this->logger->info("Order [{$this->getOrderIncrementId($order)}] - no P21 shipments found");
            return;
        }

        try {
            // Iterate through p21 shipments and create Magento shipments
            foreach ($p21PickTicketStubs as $p21PickTicketStub) {

                $p21PickTicketNumber = $this->api->parsePickTicketNumber($p21PickTicketStub);
                $p21InvoiceNumber = $this->api->parsePickTicketInvoiceNumber($p21PickTicketStub);

                if (!$p21InvoiceNumber){
                    $this->logger->info("Order [{$this->getOrderIncrementId($order)}] - skipped shipment for pick ticket [{$p21PickTicketNumber}] (no invoice found)");
                    continue;
                }

                $p21InvoiceDetails = $this->api->getInvoice($p21InvoiceNumber, $params);
                $p21InvoicedLineItems = $this->api->getInvoiceLineItems($p21InvoiceNumber, $params);
                $p21OrderShippingAmount += $this->api->parseInvoiceFreight($p21InvoiceDetails);
                $totalOrderTaxBasedOnP21Invoices += $this->api->parseInvoiceTaxAmount($p21InvoiceDetails);

                /**
                 * Create a shipment corresponding to items in this pick ticket
                 */
                if (!$this->pickListRepository->isPickListProcessed($p21PickTicketNumber) && $order->canShip()) {

                    $p21PickTicket = $this->api->getPickTicket($p21OrderNo, $p21PickTicketNumber);
                    $p21PickTicketItems  = $this->api->getPickTicketLineItems($p21OrderNo, $p21PickTicketNumber);
                    $p21PickTicketCarrierId = $this->api->parsePickTicketCarrierId($p21PickTicket);
                    $p21PickTicketTracking = $this->api->parsePickTicketTracking($p21PickTicket);

                    $p21PickTicketCarrierCode = $this->shippingTrackingCarrierIdentifier->getCarrierCode($p21PickTicketTracking);
                    if (!$p21PickTicketCarrierCode || $this->shippingTrackingCarrierIdentifier->isCarrierCodeCustom($p21PickTicketCarrierCode) ) {
                        $p21PickTicketCarrierCode = $p21Carriers[$p21PickTicketCarrierId];
                    }

                    // Build an array of skus included in particular shipment
                    $shipments[$p21PickTicketNumber] = [];
                    $shipmentItems = [];
                    foreach ($p21PickTicketItems as $p21ShipmentItem) {
                        $p21Sku = $this->api->parseLineItemSku($p21ShipmentItem);
                        $p21Qty = $this->api->parseLineItemShipQty($p21ShipmentItem);

                        foreach ($order->getAllItems() as $orderItem) {
                            if ($orderItem->getSku() == $p21Sku) {
                                $shipmentQty = min($p21Qty, $orderItem->getQtyOrdered() - $orderItem->getQtyShipped() - $orderItem->getQtyCanceled());
                                if ($shipmentQty > 0 && !key_exists($orderItem->getId(), $shipmentItems)){
                                    $shipmentItems[$orderItem->getId()] = $shipmentQty;
                                    $p21Qty -= $shipmentQty;
                                    if ($p21Qty <= 0) break;
                                }
                            }
                        }

                        if (key_exists($p21Sku, $shipments[$p21PickTicketNumber])){
                            $shipments[$p21PickTicketNumber][$p21Sku] += $p21ShipmentItem['ship_quantity'];
                        } else {
                            $shipments[$p21PickTicketNumber][$p21Sku] = $p21ShipmentItem['ship_quantity'];
                        }
                    }

                    // Create shipment record corresponding to p21 shipment
                    $orderShipment = $this->convertOrder->toShipment($order);
                    $shipmentIsEmpty = true;
                    $skusInShipment = [];

                    // Add skus to shipment
                    foreach ($order->getAllItems() as $orderItem) {

                        // Check virtual item and item Quantity
                        if (!$orderItem->getQtyToShip() || $orderItem->getIsVirtual()) {
                            continue;
                        }

                        // Use only sku and qty included in particular p21 shipment
                        if (key_exists($orderItem->getId(), $shipmentItems)) {
                            $shipmentItem = $this->convertOrder->itemToShipmentItem($orderItem)->setQty($shipmentItems[$orderItem->getId()]);
                            $orderShipment->addItem($shipmentItem);
                            $shipmentIsEmpty = false;
                            $skusInShipment[] = $orderItem->getSku();
                        }
                    }

                    if (!$shipmentIsEmpty) {
                        $orderShipment->register();
                        $orderShipment->getOrder()->setIsInProcess(true);

                        if ($p21PickTicketTracking) {
                            $trackingData = [
                                'carrier_code' => $p21PickTicketCarrierCode,
                                'title' => $this->getP21CarrierName($p21PickTicketCarrierId),
                                'number' => $p21PickTicketTracking
                            ];
                            $track = $this->trackFactory->create()->addData($trackingData);
                            $orderShipment->addTrack($track);
                        }

                        try {
                            $extensionAttributes = $orderShipment->getExtensionAttributes();
                            $sourceId = $p21PickTicket['location_id'];
                            $sourceCode = $this->getSourceCodeById($sourceId);
                            $extensionAttributes->setSourceCode($sourceCode);
                            $orderShipment->setExtensionAttributes($extensionAttributes);

                            // Save created order shipment
                            $orderShipment->save();
                            $orderShipment->getOrder()->save();

                            $this->logger->info("Order [{$this->getOrderIncrementId($order)}] - shipment created for pickticket [$p21PickTicketNumber]");

                            // Send shipment email

                            if ($this->scopeConfig->getValue('p21/debug/enable_shipment_emails')) {
                                $this->shipmentNotifier->notify($orderShipment);
                            }

                            $orderShipment->save();

                            // trigger save on the order again so the invoices are reflected properly
                            $orderShipment->getOrder()->save();

                            // mark pick list as processed so we don't try to create shipment for it on subsequent imports
                            $this->pickListRepository->get($p21PickTicketNumber)->markAsProcessed();
                            $orderDataChanged = true;

                        } catch (\Exception $e) {
                            $this->logger->error('Ripen_Prophet21 - ' . __CLASS__ . ' - ' . __METHOD__ . ' - Line ' . __LINE__ . ' - Message : ' . $e->getMessage());
                        }
                    }

                } else {
                    $this->logger->info("Order [{$this->getOrderIncrementId($order)}] - skip shipment creation for pick ticket [{$p21PickTicketNumber}]");
                }

                /**
                 * Create an invoice corresponding to items in this pick ticket.
                 *
                 * Note: if P21 invoice is marked as paid, it's fully paid. P21 invoice can't
                 * be paid only for partial quantities.
                 */

                $invoicedItems = [];
                foreach($p21InvoicedLineItems as $p21InvoicedLineItem){
                    $p21Sku = $this->api->parseLineItemSku($p21InvoicedLineItem);
                    $p21Qty = $this->api->parseLineItemQtyShipped($p21InvoicedLineItem);

                    /**
                     * Find an order item  to be included to the invoice by SKU.
                     * For orders with duplicate SKUs we need to identify order items that
                     * haven't been fully invoiced yet. There is no relationship between p21's
                     * order_item and invoice_item, so we can't tell which specific order line item
                     * with the same SKU is included to what invoice. But that shouldn't be a problem,
                     * as long as all order items are eventually invoiced.
                     */

                    foreach ($order->getAllItems() as $orderItem) {
                        if ($orderItem->getSku() == $p21Sku) {
                            $invoiceQty = min($p21Qty, $orderItem->getQtyOrdered() - $orderItem->getQtyInvoiced() - $orderItem->getQtyCanceled());
                            if ($invoiceQty > 0 && !key_exists($orderItem->getId(), $invoicedItems)){
                                $invoicedItems[$orderItem->getId()] = $invoiceQty;
                                $p21Qty -= $invoiceQty;
                                if ($p21Qty <= 0) break;
                            }
                        }
                    }
                }

                if (!$this->invoiceRepository->isInvoiceProcessed($p21InvoiceNumber) && $this->api->parsePaidInFull($p21InvoiceDetails)){
                    $this->createInvoice($order, $invoicedItems, $p21InvoiceDetails, $p21PickTicketNumber);
                    $orderDataChanged = true;
                } else if ($this->invoiceRepository->isInvoiceProcessed($p21InvoiceNumber)) {
                    $this->logger->info("Order [{$this->getOrderIncrementId($order)}] - skip invoice  [{$p21InvoiceNumber}] - already marked as processed");
                } else {
                    $this->logger->info("Order [{$this->getOrderIncrementId($order)}] - skip invoice  [{$p21InvoiceNumber}] - not marked as paid in full on p21 data");
                }
            }

            $p21OrderLineItems = $this->api->getOrderLineItems($p21OrderNo, $params);
            $totalOrderTaxBasedOnP21OrderItems = 0;
            $totalOrderAmountBasedOnP21OrderItems = 0;
            $totalDiscountBasedOnP21OrderItems = 0;
            $totalTaxAmountBasedOnP21OrderItems = 0;

            foreach ($p21OrderLineItems as $p21OrderLineItem) {
                $totalOrderTaxBasedOnP21OrderItems += $this->api->parseOrderLineItemTaxAmount($p21OrderLineItem);
                $p21LineItemTotalPrice = $this->api->parseLineItemExtendedPrice($p21OrderLineItem);
                $totalOrderAmountBasedOnP21OrderItems += $p21LineItemTotalPrice;
                $totalTaxAmountBasedOnP21OrderItems += $this->api->parseOrderLineItemTaxAmount($p21OrderLineItem);

                if ($p21LineItemTotalPrice < 0) {
                    $totalDiscountBasedOnP21OrderItems += abs($p21LineItemTotalPrice);
                }
            }

            /**
             * TODO: double check that this logic is legit. Mainly confirm that p21 order line item
             * tax excludes shipping tax
             */
            $p21TotalShippingTax = max(0, $totalOrderTaxBasedOnP21Invoices - $totalOrderTaxBasedOnP21OrderItems);
            $p21TotalShippingAmountInclTax = $p21OrderShippingAmount + $p21TotalShippingTax;

            // Total across line items plus discount (it was substructed while iterating through line items -
            // discount is included to P21 as separate line item
            $subtotal = $totalOrderAmountBasedOnP21OrderItems + $totalDiscountBasedOnP21OrderItems;

            // Subtotal plus total tax
            $subtotalInclTax = $subtotal + $totalTaxAmountBasedOnP21OrderItems;

            // Total across line items (already has discount substructed) plus shipping
            $grandTotal = $totalOrderAmountBasedOnP21OrderItems + $p21TotalShippingAmountInclTax;


            $orderTotals = [
                $order->getData('shipping_amount'),
                $order->getData('shipping_incl_tax'),
                $order->getData('grand_total'),
                $order->getData('subtotal'),
                $order->getData('subtotal_incl_tax')
            ];
            $orderTotals = array_map('floatval', $orderTotals);

            $recalculatedOrderTotals = [
                $p21OrderShippingAmount,
                $p21TotalShippingAmountInclTax,
                $grandTotal,
                $subtotal,
                $subtotalInclTax
            ];
            $recalculatedOrderTotals = array_map('floatval', $recalculatedOrderTotals);

            if ( $orderDataChanged || $orderTotals!= $recalculatedOrderTotals){

                $order->setShippingAmount($p21OrderShippingAmount);
                $order->setBaseShippingAmount($p21OrderShippingAmount);
                $order->setShippingInclTax($p21TotalShippingAmountInclTax);
                $order->setBaseShippingInclTax($p21TotalShippingAmountInclTax);
                $order->setSubtotal($subtotal);
                $order->setBaseSubtotal($subtotal);
                $order->setSubtotalInclTax($subtotalInclTax);
                $order->setBaseSubtotalInclTax($subtotalInclTax);
                $order->setGrandTotal($grandTotal);
                $order->setBaseGrandTotal($grandTotal);

                $order->save();
                $this->logger->info("Order [{$this->getOrderIncrementId($order)}] - saved");
            } else {
                $this->logger->info("Order [{$this->getOrderIncrementId($order)}] - not saving (nothing changed)");
            }

            $this->logger->info("Order [{$this->getOrderIncrementId($order)}] - processed");

        } catch (\Exception $e) {
            $this->logger->error("Order [{$this->getOrderIncrementId($order)}] - failed to import: " . $e->getMessage());
        }
    }

    /**
     * @param int $carrierId
     * @return string
     * @throws \Ripen\Prophet21\Exception\P21ApiException
     */
    protected function getP21CarrierName($carrierId): string
    {
        static $carriers = null;
        if (empty($carriers)) {
            $carriers = $this->api->getCarriers();
        }

        if (! isset($carriers[$carrierId])) {
            throw new \UnexpectedValueException('No P21 carrier found matching provided carrier ID.');
        }

        return $carriers[$carrierId];
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @return mixed
     */
    protected function getOrderIncrementId($order)
    {
        return $order->getIncrementId();
    }

    protected function getP21OrderIds()
    {
        $specificOrders = $this->scopeConfig->getValue('p21/debug/individual_order_ids');
        if ($specificOrders) {
            return array_map("trim", explode(',', $specificOrders));
        }
        return [];
    }

    protected function getP21CustomerIds()
    {
        $specificCustomers = $this->scopeConfig->getValue('p21/debug/individual_customer_ids');
        if ($specificCustomers) {
            return array_map("trim", explode(',', $specificCustomers));
        }

        $excludedCustomers = $this->scopeConfig->getValue('p21/debug/exclude_individual_customer_ids');
        $excludedCustomers = array_map("trim", explode(',', $excludedCustomers));

        $customerIds = [];

        $retailP21CustomerId = $this->customerHelper->getRetailP21CustomerId();

        // Include web customer
        array_push($customerIds, $retailP21CustomerId);

        $wholesaleStoreId = $this->multistoreHelper->getWholesaleStoreId();

        // Include customers with non-retail ERP Customer ID
        $wholesaleCustomers = $this->customerCollectionFactory->create()
            ->addAttributeToSelect("*")
            ->addAttributeToFilter("store_id", ["eq" => $wholesaleStoreId])
            ->addAttributeToFilter(
                [
                    ["attribute" => CustomerHelper::P21_CUSTOMER_ID_FIELD, "notnull" => true],
                    ["attribute" => CustomerHelper::P21_CUSTOMER_ALTERNATE_IDS_FIELD, "notnull" => true]
                ],
                "",
                "left"
            );

        foreach ($wholesaleCustomers as $wholesaleCustomer) {
            if (! empty($wholesaleCustomer->getData(CustomerHelper::P21_CUSTOMER_ID_FIELD))) {
                array_push($customerIds, $wholesaleCustomer->getData(CustomerHelper::P21_CUSTOMER_ID_FIELD));
            }

            if (! empty($wholesaleCustomer->getData(CustomerHelper::P21_CUSTOMER_ALTERNATE_IDS_FIELD))) {
                $customerAlternateIds = array_map("trim", explode(',', $wholesaleCustomer->getData(CustomerHelper::P21_CUSTOMER_ALTERNATE_IDS_FIELD)));
                $customerIds = array_merge($customerIds, $customerAlternateIds);
            }
        }

        $filteredCustomers = array_diff($customerIds, $excludedCustomers);
        $filteredCustomers = array_unique($filteredCustomers);

        return $filteredCustomers;
    }

    /**
     * Create a new Magento order
     * @param $p21OrderData
     * @return array
     */
    public function createMageOrder($p21OrderData)
    {
        $websiteId = $p21OrderData['website_id'];
        $storeId = $p21OrderData['store_id'];
        $p21CustomerCode = $p21OrderData['customer_code'];
        $p21ContactId = $p21OrderData['contact_id'] ?? null;
        $historyRecordMessage = "Order from outside Magento imported on ". date('Y-m-d', time());

        try {
            $this->emulation->startEnvironmentEmulation($storeId);

            $store = $this->storeManager->getStore($storeId);

            $customer = $this->customerFactory->create();
            $customer->setWebsiteId($websiteId);
            $customer->loadByEmail($p21OrderData['email']);

            // If customer is not found by email, find it by p21 customer code
            // Multiple Magento customers can have the same p21 customer code,
            // so we need to select just one

            if (!$customer->getEntityId()) {
                $customers = $this->customerCollectionFactory->create()
                    ->addAttributeToSelect("*")
                    ->addAttributeToFilter(
                        [
                            ["attribute" => CustomerHelper::P21_CUSTOMER_ID_FIELD, "eq" => $p21CustomerCode],
                            ["attribute" => CustomerHelper::P21_CUSTOMER_ALTERNATE_IDS_FIELD, [ "finset" => [$p21CustomerCode]]]
                        ]
                    );
                if ($customers->getSize() == 1){
                    $customer = $customers->getFirstItem();
                } else if ($customers->getSize() > 1){
                    /**
                     * If there are multiple Magento accounts with the given customer ID, check for customers that
                     * explicitly matches the order's contact id. We skip any orders where we can’t find a matching
                     * Magento account either by email address or explicit contact ID.
                     */
                    $customersWithMatchingContactIds = [];
                    foreach($customers as $customer) {
                        $customerContactId = $this->customerHelper->getP21ContactId($customer);

                        if ($customerContactId == $p21ContactId && !empty($customerContactId) && !empty($p21ContactId)) {
                            $customersWithMatchingContactIds[] = $customer;
                        }
                    }

                    // If there's a single contact ID match, use that account
                    if (count($customersWithMatchingContactIds) == 1) {
                        $customer = $customersWithMatchingContactIds[0];
                    }
                    // Otherwise, check for a single account match based on contact email address
                    else {
                        $p21Contact = $this->api->getContactById($p21ContactId);
                        if (!empty($p21Contact)) {
                            $p21ContactEmail = $this->api->parseCustomerContactEmailAddress($p21Contact);

                            foreach($customers as $searchedCustomer) {
                                if ($searchedCustomer->getEmail() == $p21ContactEmail) {
                                    $p21PrimaryContact = $p21CustomerContact;
                                    $customer = $searchedCustomer;
                                    $this->logger->info("Importing order [{$p21OrderData['p21_order_no']}] - more than one customer found with code [{$p21CustomerCode}] - use first contact [{$p21ContactEmailAddress}]");
                                    break;
                                }
                            }
                        }

                        // if at this point we haven't gotten a customer account match, skip the order
                        if (!$customer->getEntityId()) {
                            return ['error' => 1, 'message' => "more than one customer found with code [{$p21CustomerCode}], and no explicit contact id [{$p21ContactId}] match found - skipping"];
                        }
                    }
                }
            }

            // If Magento customer is not found by email or p21 customer code or contact id, create a new customer
            if (!$customer->getEntityId()) {

                $customerEmail = (! empty($p21PrimaryContact)) ? $this->api->parseCustomerContactEmailAddress($p21PrimaryContact) :  $p21OrderData['email'];
                $customerFirstName = (! empty($p21PrimaryContact)) ? $this->api->parseCustomerContactFirstName($p21PrimaryContact) : $p21OrderData['shipping_address']['firstname'];
                $customerLastName = (! empty($p21PrimaryContact)) ? $this->api->parseCustomerContactLastName($p21PrimaryContact) :  $p21OrderData['shipping_address']['lastname'];
                $password = uniqid();

                $customer->setWebsiteId($websiteId)
                    ->setStore($store)
                    ->setFirstname($customerFirstName)
                    ->setLastname($customerLastName)
                    ->setEmail($customerEmail)
                    ->setPassword($password);
                $customer->save();
            }

            // Make sure p21 customer code is set for the customer
            if (!$customer->getData(CustomerHelper::P21_CUSTOMER_ID_FIELD)){
                $customerData = $customer->getDataModel();
                $customerData->setCustomAttribute(CustomerHelper::P21_CUSTOMER_ID_FIELD, $p21CustomerCode);
                $customer->updateData($customerData);
                $customer->save();
            }

            $quote = $this->quote->create();
            $quote->setStore($store);
            $customer = $this->customerRepositoryInterface->getById($customer->getEntityId());
            $quote->setCurrency();
            $quote->assignCustomer($customer);
            $p21Subtotal = 0;
            $p21TotalQty = 0;
            $p21TotalTax = 0;
            $p21TotalInclTax = 0;
            $stockId = $this->stockByWebsiteIdResolver->execute($websiteId)->getStockId();
            $errorMessage = '';

            foreach ($p21OrderData['items'] as $p21Item) {

                $p21OrderedQuantity = $p21Item['qty'];
                $p21CancelledQuantity = $p21Item['qty_canceled'];
                $p21QtyAfterCancellations = $p21OrderedQuantity - $p21CancelledQuantity;
                $p21TaxPerItem = $p21Item['tax_amount'] / $p21OrderedQuantity;

                $product = $this->productFactory->create()->load($p21Item['product_id']);
                $product->setStatus(1);
                $this->productHelper->setSkipSaleableCheck(true);
                $product->setIsSuperMode(true);
                $quoteItem = $this->cartItemFactory->create();
                $quoteItem->setProduct($product);

                $quoteItem->setData('p21_unique_id', $p21Item['p21_unique_id']);
                $quoteItem->setData('uom', $p21Item['uom']);
                $quoteItem->setQty($p21OrderedQuantity);
                $quoteItem->setQtyCanceled($p21CancelledQuantity);
                $quoteItem->setPrice($p21Item['price']);
                $quoteItem->setBasePrice($p21Item['price']);
                $quoteItem->setPriceInclTax($p21Item['price'] + $p21TaxPerItem);
                $quoteItem->setBasePriceInclTax($p21Item['price'] + $p21TaxPerItem);
                $quoteItem->setRowTotal($p21Item['price'] * $p21QtyAfterCancellations);
                $quoteItem->setBaseRowTotal($p21Item['price'] * $p21QtyAfterCancellations);
                $quoteItem->setRowTotalInclTax(($p21Item['price'] + $p21TaxPerItem) * $p21QtyAfterCancellations);
                $quoteItem->setBaseRowTotalInclTax(($p21Item['price'] + $p21TaxPerItem) * $p21QtyAfterCancellations);
                $quoteItem->setCustomPrice($p21Item['price']);
                $quoteItem->setOriginalCustomPrice($p21Item['price']);
                $quoteItem->setTaxPercent($p21Item['tax_percent']);
                $quoteItem->setTaxAmount($p21Item['tax_amount']);
                $quoteItem->setBaseTaxAmount($p21Item['tax_amount']);
                $quoteItem->getProduct()->setIsSuperMode(true);

                $isSalable = $this->isProductSalableForRequestedQty->execute($product->getSku(), $stockId, $p21OrderedQuantity);
                if ($isSalable->isSalable() == false) {
                    $error = implode(', ', array_map(function ($err) { return $err->getMessage(); }, $isSalable->getErrors()));
                    $errorMessage .= "[{$error}]";
                }

                $quote->addItem($quoteItem);

                $p21Subtotal += $p21Item['price'] * $p21QtyAfterCancellations;
                $p21TotalInclTax += ($p21Item['price'] + $p21TaxPerItem) * $p21QtyAfterCancellations;

                $p21TotalQty += $p21QtyAfterCancellations;
                $p21TotalTax += $p21Item['tax_amount'];
            }

            if($errorMessage){
                throw new \Exception($errorMessage);
            }

            $quote->setGrandTotal($p21TotalInclTax);
            $quote->setBaseGrandTotal($p21TotalInclTax);
            $quote->setSubtotal($p21Subtotal);
            $quote->setBaseSubtotal($p21Subtotal);
            $quote->setSubtotalWithDiscount($p21Subtotal);
            $quote->setBaseSubtotalWithDiscount($p21Subtotal);
            $quote->setItemsQty($p21TotalQty);
            $quote->setComments($historyRecordMessage);
            $quote->setIsActive(false);
            $quote->save();

            $quote->getBillingAddress()->addData($p21OrderData['billing_address']);
            $shippingAddress = $quote->getShippingAddress()->addData($p21OrderData['shipping_address']);
            $shippingAddress->setCollectShippingRates(true);
            $shippingAddress->collectShippingRates();
            $shippingAddress->save();

            $shippingAddress->setShippingMethod($p21OrderData['shipping_method']);
            $shippingAddress->setShippingDescription($p21OrderData['shipping_description']);

            $paymentMethod = $p21OrderData['payment_method'];
            $quote->setPaymentMethod($paymentMethod);
            $quote->setInventoryProcessed(true);

            $quote->setIsSuperMode(true);
            $quote->setTotalsCollectedFlag(true);
            $quote->getPayment()->importData(['method' => $paymentMethod,'po_number'=>$p21OrderData['po_number']]);
            $quote->save();

            $order = $this->quoteManagement->submit($quote);

            /**
             * Set order totals manually
             */

            // Temporarily set shipping amount to 0. It will be updated later on during the Process Order phase
            $totalShippingAmount = 0;

            // No discount fields are found in API response. Leave it as 0.
            $totalDiscountAmount = 0;

            $calculatedP21GrandTotal = $p21TotalInclTax + $totalShippingAmount - $totalDiscountAmount;

            if (!$order){
                return ['error' => 1 ,'message' => "Order [{$p21OrderData['p21_order_no']}] for customer [{$p21CustomerCode}] failed to import"];
            }

            $order->setShippingAmount($totalShippingAmount);
            $order->setBaseShippingAmount($totalShippingAmount);
            $order->setShippingInclTax($totalShippingAmount);
            $order->setBaseShippingInclTax($totalShippingAmount);

            $order->setDiscountAmount($totalDiscountAmount);
            $order->setBaseDiscountAmount($totalDiscountAmount);
            $order->setSubtotal($p21Subtotal);
            $order->setBaseSubtotal($p21Subtotal);
            $order->setTaxAmount($p21TotalTax);
            $order->setBaseTaxAmount($p21TotalTax);
            $order->setBaseSubtotalInclTax($p21TotalInclTax);
            $order->setSubtotalInclTax($p21TotalInclTax);
            $order->setGrandTotal($calculatedP21GrandTotal);
            $order->setBaseGrandTotal($calculatedP21GrandTotal);

            $order->setBaseTotalDue($calculatedP21GrandTotal);
            $order->setTotalDue($calculatedP21GrandTotal);

            $order->setTotalQtyOrdered($p21TotalQty);

            $order->setData(CustomerHelper::P21_CUSTOMER_ID_FIELD, $p21CustomerCode);
            if ($p21OrderData['p21_order_no']) {
                $order->setData('p21_order_no', $p21OrderData['p21_order_no']);
            }
            if ($p21OrderData['web_orders_uid']) {
                $order->setData('web_orders_uid', $p21OrderData['web_orders_uid']);
            }
            if ($p21OrderData['po_number']) {
                $order->setData('po_number', $p21OrderData['po_number']);
            }

            $order->setCreatedAt($p21OrderData['order_date']);

            foreach ($order->getAllItems() as $orderItem) {
                foreach($p21OrderData['items'] as $p21Item){
                    if ($orderItem->getData('p21_unique_id') == $p21Item['p21_unique_id']){
                        $orderItem->setQtyCanceled($p21Item['qty_canceled']);
                    }
                }

                $orderItem->setQtyBackordered(0);
                $orderItem->save();
            }

            if ($this->scopeConfig->getValue('p21/integration/use_p21_order_numbers')) {
                $order->setIncrementId($p21OrderData['p21_order_no']);
            }

            $order->save();

            $order->addStatusHistoryComment($historyRecordMessage);

            $this->emulation->stopEnvironmentEmulation();

            return $order;

        } catch (\Exception $e) {
            return ['error' => 1 ,'message' => $e->getMessage()];
        }

    }

    /**
     * @param int $sourceId
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getSourceCodeById($sourceId)
    {
        $source = $this->sourceRepository->get($sourceId);
        if (!$source) {
            $source = $this->scopeConfig->getValue('p21/feeds/default_inventory_source_id');
        }
        return strtolower($source->getName());
    }

    public function getDefaultShippingMethodCode()
    {
        return $this->scopeConfig->getValue('p21/feeds/default_shipping_method_code');
    }


    /**
     * @param $country
     * @return string|null
     */
    public function formatCountryId($country)
    {
        $countryCode = !$country || $country == 'USA' ? 'US' : $country;
        try {
            $country = $this->countryFactory->create()->loadByCode($countryCode);
            return $countryCode;
        } catch (\Exception $e) {
            return null;
        }
    }
}
