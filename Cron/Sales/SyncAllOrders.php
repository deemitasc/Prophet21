<?php

namespace Ripen\Prophet21\Cron\Sales;

/**
 * Class SyncAllOrders
 * @package Ripen\Prophet21\Cron\Sales
 */
class SyncAllOrders
{
    /**
     * @var \Ripen\Prophet21\Model\OrderSync
     */
    protected $orderSync;

    public function __construct(
        \Ripen\Prophet21\Model\OrderSync $orderSync
    ) {
        $this->orderSync = $orderSync;
    }

    /**
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute()
    {
        $this->orderSync->sync(false);
    }
}
