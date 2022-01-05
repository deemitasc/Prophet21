<?php

namespace Ripen\Prophet21\Setup\Patch\Data;

use Magento\Customer\Api\CustomerMetadataInterface;
use Magento\Customer\Model\Customer;
use Magento\Customer\Setup\CustomerSetup;
use Magento\Eav\Model\Entity\Attribute\Set as AttributeSet;
use Magento\Eav\Model\Entity\Attribute\SetFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class AddCustomerErpAlternateIds implements DataPatchInterface
{
    /** @var ModuleDataSetupInterface */
    private $moduleDataSetup;

    /**
     * @var \Magento\Eav\Model\AttributeRepository
     */
    private $attributeRepository;

    /**
     * @var \Magento\Customer\Setup\CustomerSetupFactory
     */
    private $customerSetupFactory;

    /**
     * @var SetFactory
     */
    private $attributeSetFactory;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        \Magento\Eav\Model\AttributeRepository $attributeRepository,
        \Magento\Customer\Setup\CustomerSetupFactory $customerSetupFactory,
        SetFactory $attributeSetFactory
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->attributeRepository = $attributeRepository;
        $this->customerSetupFactory = $customerSetupFactory;
        $this->attributeSetFactory = $attributeSetFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function apply()
    {
        /** @var CustomerSetup $customerSetup */
        $customerSetup = $this->customerSetupFactory->create(['setup' => $this->moduleDataSetup]);
        $customerEntity = $customerSetup->getEavConfig()->getEntityType('customer');

        /** @var AttributeSet $attributeSet */
        $attributeSet = $this->attributeSetFactory->create();
        $attributeSetId = $customerEntity->getDefaultAttributeSetId();
        $attributeGroupId = $attributeSet->getDefaultGroupId($attributeSetId);

        $code = 'erp_customer_alternate_ids';
        $customerSetup->addAttribute(Customer::ENTITY, $code, [
            'type' => 'text',
            'label' => 'Alternate P21 Customer Codes',
            'note' => __('Comma delimited. These are alternative P21 Customer Codes that this customer is associated with, with the P21 Customer Code above as the currently active code.'),
            'input' => 'text',
            'required' => false,
            'visible' => true,
            'is_used_in_grid' => true,
            'attribute_set_id' => $attributeSetId,
            'attribute_group_id' => $attributeGroupId,
            'system' => false,
            'position' => 1,
            'used_in_forms' => ['adminhtml_customer']
        ]);
        $attributeId = $customerSetup->getAttributeId(Customer::ENTITY, $code);

        // assign attribute to customer attribute set
        $customerSetup->addAttributeToSet(
            CustomerMetadataInterface::ENTITY_TYPE_CUSTOMER,
            CustomerMetadataInterface::ATTRIBUTE_SET_ID_CUSTOMER,
            null,
            $code
        );

        // insert entry into customer_form_attribute to make it editable
        $this->moduleDataSetup->getConnection()
            ->insertOnDuplicate(
                $this->moduleDataSetup->getTable('customer_form_attribute'),
                [
                    // adding to adminhtml_customer as only admins should be updating this field
                    ['form_code' => 'adminhtml_customer', 'attribute_id' => $attributeId],
                ]
            );
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases()
    {
        return [];
    }
}
