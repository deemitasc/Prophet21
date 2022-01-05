<?php

namespace Ripen\Prophet21\Cron\Sales;

class ImportOrders
{

    /**
     * @var \Ripen\Prophet21\Model\ImportOrders
     */
    protected $orderImport;

    public function __construct(
        \Ripen\Prophet21\Model\OrderImport $orderImport
    ) {
        $this->orderImport = $orderImport;
    }

    public function execute()
    {
        $this->orderImport->import(false);
    }
}
