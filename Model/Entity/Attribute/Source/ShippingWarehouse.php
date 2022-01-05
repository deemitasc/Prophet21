<?php

namespace Ripen\Prophet21\Model\Entity\Attribute\Source;

use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;
use Magento\Inventory\Model\SourceRepository;

/**
 * Class ShippingWarehouse
 * @package Ripen\Prophet21\Model\Entity\Attribute\Source
 */
class ShippingWarehouse extends AbstractSource
{
    /**
     * @var SourceRepository
     */
    protected $sourceRepository;

    public function __construct(
      SourceRepository $sourceRepository
    ) {
        $this->sourceRepository = $sourceRepository;
    }

    public function getAllOptions()
    {
        $sources = $this->sourceRepository->getList()->getItems();
        $options = [];

        foreach($sources as $source) {
            $sourceCode = $source->getSourceCode();

            /**
             * The associated EAV attribute backend_type is expecting an Int value, so we need to include only
             * int-based source codes
             */
            if (! ctype_digit($sourceCode)) continue;

            $options[] = [
                'label' => $source->getName(),
                'value' => (int) $sourceCode,
            ];
        }

        return $options;
    }
}
