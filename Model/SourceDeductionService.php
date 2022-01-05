<?php

namespace Ripen\Prophet21\Model;

use Magento\InventorySourceDeductionApi\Model\SourceDeductionServiceInterface;
use Magento\InventorySourceDeductionApi\Model\SourceDeductionRequestInterface;

class SourceDeductionService implements SourceDeductionServiceInterface
{
    public function execute(SourceDeductionRequestInterface $sourceDeductionRequest): void
    {
        /**
         * The purpose of this plugin is to skip inventory decrement when orders are placed or imported.
         * The inventory stock update is fully driven by ERP via inventory sync script. This is done in order to
         * avoid decrementing stock twice (one handled by Magento and another by P21 inventory sync)
         */
    }
}
