<?php

namespace Ripen\Prophet21\Model;

use Ripen\Prophet21\Exception\P21ApiException;

/**
 * Class OrderSync
 * @package Ripen\Prophet21\Model
 */
class OrderSync extends \Ripen\Prophet21\Model\AbstractIncomingOrdersFeed
{
    /**
     * @param bool $syncOnlyRecent
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function sync($syncOnlyRecent = true)
    {
        $this->logger->info('Syncing orders...');
        if (!$this->scopeConfig->getValue('p21/feeds/enable_order_import_cron')) {
            $this->logger->info('Interrupted. Disabled in admin settings.');
            return;
        }

        $p21OrderIds = $this->getP21OrderIds();

        /**
         * If historical load mode is disabled, we first create all new orders (code above)
         * and then we update all orders that have to be updated.
         */
        if (!$this->scopeConfig->getValue('p21/debug/enable_historical_order_import_mode')) {
            $lastProcessedCreatedAt = date('Y-m-d H:i:s', time());
            $sortOrder = $this->sortOrderBuilder->setField('created_at')->setDirection('DESC')->create();

            $batchIndex = 1;
            do {
                // get pending orders
                $filterStatus = $this->filterBuilder
                    ->setField('status')
                    ->setConditionType('nin')
                    ->setValue([\Magento\Sales\Model\Order::STATE_COMPLETE, \Magento\Sales\Model\Order::STATE_CANCELED])
                    ->create();
                $this->searchCriteriaBuilder->addFilters([$filterStatus]);

                if ($p21OrderIds) {
                    $this->logger->info("Limiting sync to specific orders from debug settings: [" . implode(',', $p21OrderIds) . "]");

                    $filterOrderNumbers = $this->filterBuilder
                        ->setField('p21_order_no')
                        ->setConditionType('in')
                        ->setValue($p21OrderIds)
                        ->create();
                    $this->searchCriteriaBuilder->addFilters([$filterOrderNumbers]);
                }

                if ($syncOnlyRecent) {
                    $days = $this->scopeConfig->getValue('p21/feeds/order_sync_recent_time_window');
                    $cutoffDate = date('Y-m-d', strtotime("-{$days} days"));

                    $this->logger->info("Limiting sync to recent orders created after [{$cutoffDate}]");

                    // get only recent orders
                    $filterDate = $this->filterBuilder
                        ->setField('created_at')
                        ->setConditionType('gt')
                        ->setValue($cutoffDate)
                        ->create();
                    $this->searchCriteriaBuilder->addFilters([$filterDate]);
                } else {
                    $this->logger->info('Not limiting sync to recent orders');
                }

                $filterCreatedAt = $this->filterBuilder
                    ->setField('created_at')
                    ->setConditionType('lt')
                    ->setValue($lastProcessedCreatedAt)
                    ->create();
                $this->searchCriteriaBuilder->addFilters([$filterCreatedAt]);

                $searchCriteria = $this->searchCriteriaBuilder->create();
                $searchCriteria->setSortOrders([$sortOrder]);
                $searchCriteria->setPageSize(self::ORDER_SYNC_BATCH_SIZE);

                $orders = $this->orderRepository->getList($searchCriteria);
                $this->logger->info("Processing batch [{$batchIndex}] with [" . count($orders) . "] orders created before [{$lastProcessedCreatedAt}]");

                foreach ($orders as $order) {
                    try {
                        $this->logger->info("Syncing order [Increment ID: {$this->getOrderIncrementId($order)}][P21 ID: {$order->getData('p21_order_no')}]...");

                        // get weborder via api from simple apps
                        $p21WebOrder = $this->api->getWebOrder($order->getWebOrdersUid());

                        // retrieve and save p21 order number if available
                        if (!$order->getData('p21_order_no')) {
                            $p21OrderNo = $this->api->parseP21OrderNumber($p21WebOrder);
                            if (!$p21OrderNo) {
                                $this->logger->info("Order [{$this->getOrderIncrementId($order)}] - skipped (no P21 order number)");
                                continue;
                            }

                            if ($this->scopeConfig->getValue('p21/integration/use_p21_order_numbers')) {
                                $order->addStatusHistoryComment("Order [{$this->getOrderIncrementId($order)}] updated to P21 order number [$p21OrderNo]");
                                $order->setIncrementId($p21OrderNo);
                            }
                            $order->setData('p21_order_no', $p21OrderNo)->save();
                            $this->logger->info("Order [{$this->getOrderIncrementId($order)}] - retrieved P21 order number [$p21OrderNo]");
                        }

                        // retrieve and save po_number if available
                        $p21PONumber = $this->api->parsePONumber($p21WebOrder);
                        if (! empty($p21PONumber)) {
                            $orderPONumber = $order->getData('po_number');
                            $paymentPONumber = $order->getPayment()->getPoNumber();

                            // defer to payment po_number if that's where the PO number was set
                            if (empty($orderPONumber) && ! empty($paymentPONumber)) {
                                // only need to update po_number if the local order doesn't already have the same value, meaning
                                // that the po_number on P21 originated from the local order/site if they are the same
                                if ($paymentPONumber !== $p21PONumber) {
                                    $order->getPayment()->setPoNumber($p21PONumber)->save();
                                }
                            }
                            // save PO Number to order by default if none of the previous conditions matched
                            else {
                                // only need to update po_number if the local order doesn't already have the same value, meaning
                                // that the po_number on P21 originated from the local order/site if they are the same
                                if ($orderPONumber !== $p21PONumber) {
                                    $order->setData('po_number', $p21PONumber)->save();
                                }
                            }
                        }

                        // order is now considered "in progress", or needs to be updated if there's an update in shipment(s)
                        $this->processOrder($order);
                    } catch (P21ApiException $e) {
                        $this->logger->error('Ripen_Prophet21 - ' . __CLASS__ . ' - ' . __METHOD__ . ' - Line ' . __LINE__ . ' - Message : ' . $e->getMessage());
                    }
                    $lastProcessedCreatedAt = $order->getCreatedAt();
                }

                $batchIndex++;
            } while (count($orders) >= self::ORDER_SYNC_BATCH_SIZE);
        }

        $this->logger->info('Syncing orders completed');
        $this->logger->info('---------------------------');
    }
}
