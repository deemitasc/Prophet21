<?php
/**
 * Plugin to add additional item data into cart item
 */

namespace Ripen\Prophet21\Plugin;


class GetAdditionalCustomerItemData
{
    /**
     * @param \Magento\Checkout\CustomerData\ItemInterface $subject
     * @param array $result
     * @param \Magento\Quote\Model\Quote\Item $item
     * @return array
     */
    public function afterGetItemData(
        \Magento\Checkout\CustomerData\ItemInterface $subject,
        array $result,
        \Magento\Quote\Model\Quote\Item $item
    ) {
        if (! isset($result['uom'])) {
            $result['uom'] = $item->getData('uom') ?? '';
        }

        if (isset($result['product_name']) && ! empty($result['uom'])) {
            $result['product_name'] .=  ' (' . $result['uom'] . ')';
        }

        return $result;
    }
}
