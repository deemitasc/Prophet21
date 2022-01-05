<?php

namespace Ripen\Prophet21\Model\Config;

class StockLocationsModes implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => '', 'label' => __('Disabled')],
            ['value' => 'all', 'label' => __('Enabled for all customers')],
            ['value' => 'wholesale', 'label' => __('Enabled for wholesale customers')]
        ];
    }
}
