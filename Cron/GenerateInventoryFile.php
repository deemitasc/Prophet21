<?php

namespace Ripen\Prophet21\Cron;

class GenerateInventoryFile
{
    /**
     * @var \Ripen\Prophet21\Model\Inventory
     */
    protected $inventory;

    public function __construct(
        \Ripen\Prophet21\Model\Inventory $inventory
    ) {
        $this->inventory = $inventory;
    }

    public function execute()
    {
        $this->inventory->generateFile();
    }

}
