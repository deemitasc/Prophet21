<?php

namespace Ripen\Prophet21\Model\Config;

class PriceFields implements \Magento\Framework\Data\OptionSourceInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        $options = [
            ['value' => 'price1', 'label' => 'Price 1'],
            ['value' => 'price2', 'label' => 'Price 2'],
            ['value' => 'price3', 'label' => 'Price 3'],
            ['value' => 'price4', 'label' => 'Price 4'],
            ['value' => 'price5', 'label' => 'Price 5'],
            ['value' => 'price6', 'label' => 'Price 6'],
            ['value' => 'price7', 'label' => 'Price 7'],
            ['value' => 'price8', 'label' => 'Price 8'],
            ['value' => 'price9', 'label' => 'Price 9'],
            ['value' => 'price10', 'label' => 'Price 10'],
            ['value' => 'list_price', 'label' => 'Supplier List Price']
        ];

        return $options;
    }
}
