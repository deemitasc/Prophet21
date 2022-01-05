<?php

namespace Ripen\Prophet21\Setup\Patch\Data;

use Magento\Customer\Model\Customer;
use Magento\Customer\Setup\CustomerSetup;
use Magento\Eav\Model\Entity\Attribute\Set as AttributeSet;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchVersionInterface;

class AddCustomerContactId implements DataPatchInterface
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

        $this->addCustomerContactId($customerSetup);
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
    private function addCustomerContactId(\Magento\Customer\Setup\CustomerSetup $customerSetup)
    {
        $customerEntity = $customerSetup->getEavConfig()->getEntityType('customer');

        /** @var AttributeSet $attributeSet */
        $attributeSet = $this->attributeSetFactory->create();
        $attributeSetId = $customerEntity->getDefaultAttributeSetId();
        $attributeGroupId = $attributeSet->getDefaultGroupId($attributeSetId);

        $code = 'erp_contact_id';
        $customerSetup->addAttribute(Customer::ENTITY, $code, [
            'type' => 'varchar',
            'label' => 'P21 Contact ID',
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
        $attributeCode = 'erp_contact_id';

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
