<?php

namespace Ripen\Prophet21\Model;

class ShippingMethodMapper
{
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @param int $p21CarrierId
     * @return string
     * @throws \UnexpectedValueException
     */
    public function getMagentoMethodCode($p21CarrierId): string
    {
        $mapping = $this->getMethodMap();
        return empty($mapping[$p21CarrierId]) ? '' : $mapping[$p21CarrierId][0];
    }

    /**
     * @param string $magentoMethodCode
     * @return int
     * @throws \UnexpectedValueException
     */
    public function getP21CarrierId($magentoMethodCode): int
    {
        $mapping = $this->getMethodMap();
        foreach ($mapping as $p21CarrierId => $methodCodes) {
            if (in_array($magentoMethodCode, $methodCodes)) {
                return $p21CarrierId;
            }
        }
        throw new \UnexpectedValueException("No P21 carrier ID mapped to given Magento shipping method code [$magentoMethodCode].");
    }

    /**
     * Returns a map of P21 carrier codes to arrays of matching Magento shipping method codes.
     *
     * @return array
     */
    public function getMethodMap(): array
    {
        $rawData = $this->scopeConfig->getValue('p21/feeds/shipping_methods_mapping');
        $mapping = json_decode($rawData, true) ?: [];
        return array_map(function ($item) {
            return (array) $item;
        }, $mapping);
    }
}
