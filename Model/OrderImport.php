<?php

namespace Ripen\Prophet21\Model;

/**
 * Class OrderImport
 * @package Ripen\Prophet21\Model
 */
class OrderImport extends \Ripen\Prophet21\Model\AbstractIncomingOrdersFeed
{
    public function import()
    {
        $this->logger->info('Importing orders...');
        if (!$this->scopeConfig->getValue('p21/feeds/enable_order_import_cron')) {
            $this->logger->info('Interrupted. Disabled in admin settings.');
            return;
        }

        /**
         * Check if we are importing only specific orders
         */
        $p21OrderIds = $this->getP21OrderIds();

        /**
         * Orders can only be retrieved for a specific customer in Simple Apps (API)
         * So we need to get all customers first and the load orders for each customer
         */
        $p21CustomerIds = $this->getP21CustomerIds();
        $countCustomers = count($p21CustomerIds);
        $this->logger->info("Found [{$countCustomers}] customers for order import");

        $i = 0;
        foreach ($p21CustomerIds as $p21CustomerId) {
            $i++;
            $this->logger->info("[{$i}/{$countCustomers}] Checking new P21 orders for customer [P21 ID: {$p21CustomerId}] ...");

            $params['customer_id'] = $p21CustomerId;
            $params['days'] = $this->scopeConfig->getValue('p21/debug/days_limit_orders_import');
            $params['limit'] = self::ORDERS_IMPORT_LIMIT;

            try {
                $p21Orders = $this->api->getP21Orders($params);
            } catch (\Exception $e){
                $this->logger->error("Can't retrieve P21 orders for customer [{$p21CustomerId}]: {$e->getMessage()}");
                continue;
            }

            foreach ($p21Orders as $p21Order) {

                // Import a specific order (if set) or all orders placed directly in P21
                if (
                    in_array($p21Order['order_no'], $p21OrderIds) ||
                    (!$p21Order['web_reference_no'] && !$p21OrderIds)
                ) {
                    $searchCriteria = $this->searchCriteriaBuilder
                        ->addFilter('p21_order_no', $this->api->parseOrderNo($p21Order), 'eq')
                        ->create();

                    $orders = $this->orderRepository->getList($searchCriteria);
                    if (!$orders->getTotalCount()) {
                        $order = $this->createNewOrder($this->api->parseOrderNo($p21Order), $p21CustomerId);

                        /**
                         * For the historical orders load we update individual orders right after creation
                         */
                        if ($this->scopeConfig->getValue('p21/debug/enable_historical_order_import_mode') && is_object($order)){
                            $this->processOrder($order);
                        }
                    }
                }
            }
        }

        $this->logger->info('Importing orders completed');
        $this->logger->info('---------------------------');
    }
}
