<?php

namespace Ripen\Prophet21\Cron;

class GenerateProductsDataFile
{
    /**
     * @var \Ripen\Prophet21\Model\Products
     */
    protected $products;

    public function __construct(
        \Ripen\Prophet21\Model\Products $products
    ) {
        $this->products = $products;
    }

    public function execute()
    {
        $this->products->generateFile();
    }

}
