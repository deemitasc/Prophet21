<?php

namespace Ripen\Prophet21\Plugin;

use Magento\Customer\Api\AddressMetadataInterface;
use Magento\Quote\Model\Quote\Address\CustomAttributeListInterface;

class AddAddressIdQuoteAddress
{
    /**
     * @var \Magento\Customer\Api\AddressMetadataInterface
     */
    protected $addressMetadata;

    /**
     * @param \Magento\Customer\Api\AddressMetadataInterface $addressMetadata
     */
    public function __construct(
        AddressMetadataInterface $addressMetadata
    ) {
        $this->addressMetadata = $addressMetadata;
    }

    /**
     * Force quote address model to recognize 'prophet_21_id' as a custom attribute.
     *
     * @param \Magento\Quote\Model\Quote\Address\CustomAttributeListInterface $attributeList
     * @param $result
     * @return \Magento\Framework\Api\MetadataObjectInterface[]
     */
    public function afterGetAttributes(
        CustomAttributeListInterface $attributeList,
        $result
    ) {
        return $result + ['prophet_21_id' => $this->addressMetadata->getAttributeMetadata('prophet_21_id')];
    }
}
