<?php

namespace Ripen\Prophet21\Plugin;

class ToOrderItem
{

    public function aroundConvert(\Magento\Quote\Model\Quote\Item\ToOrderItem $subject,
                                  \Closure $proceed,
                                  $item,
                                  $data = []
    ) {
        $orderItem = $proceed($item, $data);

        $source = $item->getOptionByCode('source');
        if ($source) {
            $options = $orderItem->getProductOptions();
            $options['source'] = $source->getValue();
            $orderItem->setProductOptions($options);
        }

        $orderItem->setData('p21_unique_id', $item->getData('p21_unique_id'));

        return $orderItem;
    }
}
