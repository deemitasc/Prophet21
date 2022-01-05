<?php

namespace Ripen\Prophet21\Setup\Patch\Data;

use Magento\Customer\Model\Customer;
use Magento\Customer\Setup\CustomerSetup;
use Magento\Eav\Model\Entity\Attribute\Set as AttributeSet;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchVersionInterface;

class AddCustomerErpIdAndWarehouse implements DataPatchInterface
{
    /**
     * @var \Magento\Framework\Setup\ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @var \Magento\Customer\Setup\CustomerSetupFactory
     */
    private $customerSetupFactory;

    /**
     * @var \Magento\Eav\Model\Entity\Attribute\SetFactory
     */
    private $attributeSetFactory;

    /**
     * @var \Magento\Eav\Model\AttributeRepository
     */
    private $attributeRepository;

    /**
     * @param \Magento\Framework\Setup\ModuleDataSetupInterface $moduleDataSetup
     * @param \Magento\Customer\Setup\CustomerSetupFactory $customerSetupFactory
     * @param \Magento\Eav\Model\AttributeRepository $attributeRepository
     * @param \Magento\Eav\Model\Entity\Attribute\SetFactory $attributeSetFactory
     */
    public function __construct(
        \Magento\Framework\Setup\ModuleDataSetupInterface $moduleDataSetup,
        \Magento\Customer\Setup\CustomerSetupFactory $customerSetupFactory,
        \Magento\Eav\Model\AttributeRepository $attributeRepository,
        \Magento\Eav\Model\Entity\Attribute\SetFactory $attributeSetFactory
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->attributeRepository = $attributeRepository;
        $this->customerSetupFactory = $customerSetupFactory;
        $this->attributeSetFactory = $attributeSetFactory;
    }

    /**
     * {@inheritdoc}
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function apply()
    {
        /** @var CustomerSetup $customerSetup */
        $customerSetup = $this->customerSetupFactory->create(['setup' => $this->moduleDataSetup]);

        $this->addCustomerDefaultShippingWarehouseIdAttribute($customerSetup);
        $this->addErpCustomerId($customerSetup);
        $this->addCustomerErpAttributesToForms($customerSetup);
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

    /**
     * @param \Magento\Customer\Setup\CustomerSetup $customerSetup
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function addCustomerDefaultShippingWarehouseIdAttribute(\Magento\Customer\Setup\CustomerSetup $customerSetup)
    {
        $customerEntity = $customerSetup->getEavConfig()->getEntityType('customer');

        /** @var AttributeSet $attributeSet */
        $attributeSet = $this->attributeSetFactory->create();
        $attributeSetId = $customerEntity->getDefaultAttributeSetId();
        $attributeGroupId = $attributeSet->getDefaultGroupId($attributeSetId);

        $defaultLocationId = 10;

        $code = 'default_shipping_warehouse_id';
        $customerSetup->addAttribute(Customer::ENTITY, $code, [
            'type' => 'int',
            'label' => 'Default Shipping Warehouse',
            'input' => 'select',
            'source' => 'Ripen\Prophet21\Model\Entity\Attribute\Source\ShippingWarehouse',
            'required' => false,
            'visible' => true,
            'default' => $defaultLocationId,
            'attribute_set_id' => $attributeSetId,
            'attribute_group_id' => $attributeGroupId
        ]);
        $attributeId = $customerSetup->getAttributeId(Customer::ENTITY, $code);

        // Set initial value of attribute for all existing customers.
        // For performance, construct one single query manually rather than using multiple queries via EAV classes.
        $db = $this->moduleDataSetup->getConnection();
        $db->query($db->insertFromSelect(
            $db->select()->from(
                $this->moduleDataSetup->getTable('customer_entity'),
                [
                    'entity_id',
                    new \Zend_Db_Expr($attributeId),
                    new \Zend_Db_Expr($defaultLocationId)
                ]
            ),
            $this->moduleDataSetup->getTable('customer_entity_int'),
            ['entity_id', 'attribute_id', 'value'],
            $db::INSERT_IGNORE
        ));
    }

    /**
     * @param \Magento\Customer\Setup\CustomerSetup $customerSetup
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function addErpCustomerId(\Magento\Customer\Setup\CustomerSetup $customerSetup)
    {
        $customerEntity = $customerSetup->getEavConfig()->getEntityType('customer');

        /** @var AttributeSet $attributeSet */
        $attributeSet = $this->attributeSetFactory->create();
        $attributeSetId = $customerEntity->getDefaultAttributeSetId();
        $attributeGroupId = $attributeSet->getDefaultGroupId($attributeSetId);

        $code = 'erp_customer_id';
        $customerSetup->addAttribute(Customer::ENTITY, $code, [
            'type' => 'varchar',
            'label' => 'P21 Customer Code',
            'input' => 'text',
            'required' => false,
            'visible' => true,
            'is_used_in_grid' => true,
            'attribute_set_id' => $attributeSetId,
            'attribute_group_id' => $attributeGroupId,
            'used_in_forms' => ['adminhtml_customer']
        ]);
    }

    /**
     * @param \Magento\Customer\Setup\CustomerSetup $customerSetup
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function addCustomerErpAttributesToForms(\Magento\Customer\Setup\CustomerSetup $customerSetup)
    {
        // Based on logic in \Magento\Customer\Setup\CustomerSetup, in that we're doing direct DB manipulation
        // rather than working via built-in setup helpers/models

        foreach (['default_shipping_warehouse_id', 'erp_customer_id'] as $attributeCode) {
            // fetch the attribute
            $attribute = $this->attributeRepository->get(Customer::ENTITY, $attributeCode);

            // set attribute is_system to false as our attribute is not a system attribute
            $customerSetup->updateAttribute(Customer::ENTITY, $attribute->getAttributeId(), 'is_system', 0);

            // insert entry into customer_form_attribute to make it editable
            $this->moduleDataSetup->getConnection()
                ->insertOnDuplicate(
                    $this->moduleDataSetup->getTable('customer_form_attribute'),
                    [
                        // adding to adminhtml_customer as only admins should be updating default_shipping_warehouse_id and erp_customer_id
                        ['form_code' => 'adminhtml_customer', 'attribute_id' => $attribute->getAttributeId()],
                    ]
                );
        }
    }
}
