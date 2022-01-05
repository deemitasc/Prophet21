<?php

namespace Ripen\Prophet21\Setup\Patch\Data;

use Magento\Customer\Api\AddressMetadataInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class AddCustomerAddressP21IdToCustomerForm implements DataPatchInterface
{
    /** @var ModuleDataSetupInterface */
    private $moduleDataSetup;

    /**
     * @var \Magento\Eav\Model\AttributeRepository
     */
    private $attributeRepository;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param \Magento\Eav\Model\AttributeRepository $attributeRepository
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        \Magento\Eav\Model\AttributeRepository $attributeRepository
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->attributeRepository = $attributeRepository;
    }

    /**
     * Allow web-saved addresses to have a P21 ship-to ID manually entered. This allows these web-saved addresses
     * to be set as the customer default in Magento, while still being matched up to an explicit P21 ship-to.
     *
     * {@inheritdoc}
     */
    public function apply()
    {
        // Based on logic in \Magento\Customer\Setup\CustomerSetup, in such that it's using direct DB manipulation
        // rather than working via built-in setup helpers/models

        // fetch the attribute
        $attribute = $this->attributeRepository->get(AddressMetadataInterface::ENTITY_TYPE_ADDRESS, 'prophet_21_id');

        // insert entry into customer_form_attribute to make it editable
        $this->moduleDataSetup->getConnection()
            ->insertOnDuplicate(
                $this->moduleDataSetup->getTable('customer_form_attribute'),
                [
                    // adding to adminhtml version of form as only admins should be updating
                    ['form_code' => 'adminhtml_customer_address', 'attribute_id' => $attribute->getAttributeId()],
                ]
            );
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies()
    {
        return [
            \Ripen\Prophet21\Setup\Patch\Data\AddCustomerAddressP21Id::class
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases()
    {
        return [];
    }
}
