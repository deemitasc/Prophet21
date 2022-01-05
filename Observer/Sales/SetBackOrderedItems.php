<?php

namespace Ripen\Prophet21\Observer\Sales;

use Magento\Framework\Event\Observer;
use Ripen\SimpleApps\Model\Api;
use Ripen\Prophet21\Logger\Logger;

class SetBackOrderedItems  implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * @var Api
     */
    protected $api;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @param Api $api
     * @param Logger $logger
     */
    public function __construct(
        Api $api,
        Logger $logger
    ) {
        $this->api = $api;
        $this->logger = $logger;
    }

    /**
     * @param Observer $observer
     * @throws \Ripen\Prophet21\Exception\P21ApiException
     */
    public function execute(Observer $observer)
    {
        /** @var \Magento\Sales\Api\Data\OrderInterface */
        $order = $observer->getEvent()->getData('order');
        $errorMessages = [];

        /**
         * For every item ordered, check the quantity against the API and set backordered quantity as needed
         *
         * @var \Magento\Sales\Api\Data\OrderItemInterface $orderItem
         */
        foreach($order->getAllVisibleItems() as $orderItem) {
            $qty = $orderItem->getQtyOrdered();

            try {
                $netStock = $this->api->getItemNetStock($orderItem->getSku());

                if ($qty > $netStock) {
                    $orderItem->setQtyBackordered((float)($qty - $netStock));
                }
            } catch(\Exception $e) {
                // unable to fetch stock data with api call, or api has not yet been set up
                $errorMessages[] = $e->getMessage();
            }
        }

        // only log each type of api error once
        if (! empty($errorMessages)) {
            $errorMessages = array_unique($errorMessages);
            foreach($errorMessages as $errorMessage) {
                $this->logger->error($errorMessage);
            }
        }
    }
}
