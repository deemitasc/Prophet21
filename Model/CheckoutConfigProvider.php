<?php

namespace Ripen\Prophet21\Model;

class CheckoutConfigProvider implements \Magento\Checkout\Model\ConfigProviderInterface
{
    /**
     * @var \Ripen\Prophet21\Model\ShippingMethodMapper
     */
    protected $shippingMethodMapper;

    public function __construct(
        \Ripen\Prophet21\Model\ShippingMethodMapper $shippingMethodMapper
    ) {
        $this->shippingMethodMapper = $shippingMethodMapper;
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        return [
            'p21_shipping_carrier_map' => $this->shippingMethodMapper->getMethodMap()
        ];
    }
}
