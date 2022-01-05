<?php

namespace Ripen\Prophet21\Cron\Monitoring;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Ripen\Prophet21\Exception\P21ApiException;

class OrderExportCheck
{
    const CONFIG_PATH_RECIPIENTS = 'p21/email_alerts/order_export_check/recipients';
    const CONFIG_PATH_THRESHOLD = 'p21/email_alerts/order_export_check/threshold';
    const CONFIG_PATH_WINDOW = 'p21/email_alerts/order_export_check/window';

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Magento\Framework\Mail\Template\TransportBuilder
     */
    protected $transportBuilder;

    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilderFactory
     */
    protected $searchCriteriaBuilderFactory;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var \Ripen\SimpleApps\Model\Api
     */
    protected $api;

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\Mail\Template\TransportBuilder $transportBuilder
     * @param \Magento\Framework\Api\SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Mail\Template\TransportBuilder $transportBuilder,
        \Magento\Framework\Api\SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Ripen\SimpleApps\Model\Api $api
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->transportBuilder = $transportBuilder;
        $this->searchCriteriaBuilderFactory = $searchCriteriaBuilderFactory;
        $this->orderRepository = $orderRepository;
        $this->api = $api;
    }

    public function execute()
    {
        $recipients = $this->scopeConfig->getValue(self::CONFIG_PATH_RECIPIENTS);
        if (empty($recipients)) {
            return;
        }

        $orders = $this->findProblemOrders();
        if (empty($orders)) {
            return;
        }

        // If we have problem orders, verify with API to make sure they are truly not imported.
        try {
            $ordersNotYetImported = $this->api->getWebOrderNotYetImported();
            $orderUIDsNotYetImported = $this->api->parseWebOrderUidsAsArray($ordersNotYetImported);
            if (! empty($orderUIDsNotYetImported)) {
                foreach ($orders as $index => $order) {
                    // Only rely on API status if we have a web order UID, indicating a successful initial transfer to API.
                    if ($order->getWebOrdersUid() && ! in_array($order->getWebOrdersUid(), $orderUIDsNotYetImported)) {
                        unset($orders[$index]);
                    }
                }
            }
        } catch (P21ApiException $e) {
            // possible connection problem with the API
        }
        if (empty($orders)) {
            return;
        }

        $this->sendAlertEmail($orders);
    }

    /**
     * @return array
     */
    protected function findProblemOrders()
    {
        $window = (int) $this->scopeConfig->getValue(self::CONFIG_PATH_WINDOW);
        $startWindow = new \Zend_Db_Expr("NOW() - INTERVAL $window MINUTE");

        $threshold = (int) $this->scopeConfig->getValue(self::CONFIG_PATH_THRESHOLD);
        $endWindow = new \Zend_Db_Expr("NOW() - INTERVAL $threshold MINUTE");

        /** @var \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder */
        $searchCriteriaBuilder = $this->searchCriteriaBuilderFactory->create();
        /** @var \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria */
        $searchCriteria = $searchCriteriaBuilder
            ->addFilter('p21_order_no', 0, 'eq')
            ->addFilter('state', [Order::STATE_NEW, Order::STATE_PROCESSING], 'in')
            ->addFilter(OrderInterface::CREATED_AT, $startWindow, 'gteq')
            ->addFilter(OrderInterface::CREATED_AT, $endWindow, 'lteq')
            ->create();

        return $this->orderRepository->getList($searchCriteria)->getItems();
    }

    /**
     * Send a mail with the given content
     *
     * @param OrderInterface[] $orders
     */
    protected function sendAlertEmail(array $orders)
    {
        $recipients = $this->scopeConfig->getValue(self::CONFIG_PATH_RECIPIENTS);
        $emails = explode(',', $recipients);

        if (empty($emails)) {
            return;
        }

        $sender = [
            'name' => $this->scopeConfig->getValue('trans_email/ident_support/name'),
            'email' => $this->scopeConfig->getValue('trans_email/ident_support/email'),
        ];

        $data = [
            'orderTable' => $this->generateOrderTable($orders),
            'window' => $this->scopeConfig->getValue(self::CONFIG_PATH_WINDOW),
            'threshold' => $this->scopeConfig->getValue(self::CONFIG_PATH_THRESHOLD)
        ];

        try {
            $transport = $this->transportBuilder
                ->setTemplateIdentifier('p21_order_export_failure_template')
                ->setTemplateOptions(
                    [
                        'area' => \Magento\Framework\App\Area::AREA_ADMINHTML,
                        'store' => \Magento\Store\Model\Store::DEFAULT_STORE_ID,
                    ]
                )
                ->setTemplateVars($data)
                ->setFrom($sender)
                ->addTo($emails)
                ->getTransport();

            $transport->sendMessage();
        } catch (\Throwable $e) {
            // do nothing on purpose
        }
    }

    /**
     * @param OrderInterface[] $orders
     * @return string
     */
    protected function generateOrderTable(array $orders)
    {
        $columns = [
            'created_at' => 'Created At (UTC)',
            'increment_id' => 'Magento Order Number',
            'web_orders_uid' => 'Middleware Order ID'
        ];

        $html = '<table cellpadding="5" rules="rows" frame="void">';
        $html .= '<tr>';
        foreach ($columns as $column => $label) {
            $html .= "<th>$label</th>";
        }
        $html .= '</tr>';
        foreach ($orders as $order) {
            $html .= '<tr>';
            foreach ($columns as $column => $label) {
                $html .= '<td>' . ($order->getData($column) ?: '') . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</table>';

        return $html;
    }
}
