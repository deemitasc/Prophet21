<?php

namespace Ripen\Prophet21\Setup\Patch\Data;

use Magento\Customer\Api\CustomerMetadataInterface;
use Magento\Customer\Model\Customer;
use Magento\Customer\Setup\CustomerSetup;
use Magento\Eav\Model\Entity\Attribute\Set as AttributeSet;
use Magento\Eav\Model\Entity\Attribute\SetFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class AddCustomerErpSalesRepId implements DataPatchInterface
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

        $code = 'erp_sales_rep_id';
        $customerSetup->addAttribute(Customer::ENTITY, $code, [
            'type' => 'varchar',
            'label' => 'P21 Sales Rep Identity',
            'input' => 'text',
            'required' => false,
            'visible' => true,
            'is_used_in_grid' => true,
            'attribute_set_id' => $attributeSetId,
            'attribute_group_id' => $attributeGroupId,
            'system' => false,
            'position' => 25,
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
