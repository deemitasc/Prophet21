<?php

namespace Ripen\Prophet21\Plugin;

class SaveAddressIdOrderAddress
{
    /**
     * @param \Magento\Sales\Model\Order\Address $address
     * @param \Closure $proceed
     * @param string $attributeCode
     * @param mixed $attributeValue
     * @return \Magento\Sales\Model\Order\Address
     */
    public function aroundSetCustomAttribute(
        \Magento\Sales\Model\Order\Address $address,
        \Closure $proceed,
        $attributeCode,
        $attributeValue
    ) {
        if ($attributeCode === 'prophet_21_id') {
            $address->setData('prophet_21_id', $attributeValue);
            return $address;
        }

        return $proceed($attributeCode, $attributeValue);
    }
}
