<?php

namespace Ripen\Prophet21\Cron;

class GenerateImagesDataFile
{
    /**
     * @var \Ripen\Prophet21\Model\Inventory
     */
    protected $inventory;

    public function __construct(
        \Ripen\Prophet21\Model\Images $images
    ) {
        $this->images = $images;
    }

    public function execute()
    {
        $this->images->generateFile();
    }

}
