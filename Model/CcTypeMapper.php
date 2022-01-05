<?php

namespace Ripen\Prophet21\Model;

class CcTypeMapper
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
     * @param $ccType
     * @return string
     */
    public function getP21PaymentTypeCode($ccType): string
    {
        $mapping = $this->getPaymentTypeMap();
        if (empty($mapping[$ccType][0])) {
            throw new \UnexpectedValueException("No P21 payment type code mapped to given credit card  [$ccType].");
        }
        return $mapping[$ccType][0];
    }

    /**
     * Returns a map of P21 payment type ids to Magento credit card types
     *
     * @return array
     */
    public function getPaymentTypeMap(): array
    {
        $rawData = $this->scopeConfig->getValue('p21/feeds/payment_types_mapping');
        $mapping = json_decode($rawData, true) ?: [];
        return array_map(function ($item) {
            return (array) $item;
        }, $mapping);
    }
}
