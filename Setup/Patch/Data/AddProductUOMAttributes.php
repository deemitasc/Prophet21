<?php

namespace Ripen\Prophet21\Setup\Patch\Data;

use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class AddProductUOMAttributes implements DataPatchInterface
{
    /** @var ModuleDataSetupInterface */
    private $moduleDataSetup;

    /** @var EavSetupFactory */
    private $eavSetupFactory;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param EavSetupFactory $eavSetupFactory
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        EavSetupFactory $eavSetupFactory
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->eavSetupFactory = $eavSetupFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function apply()
    {
        /** @var EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        $eavSetup->addAttribute('catalog_product', 'p21_available_product_uom', [
            'type' => 'text',
            'input' => 'text',
            'label' => 'Available Product UOM',
            'note' => 'Comma-delimited list of UOM associated with this product along with its stock quantity unit size. This value is automatically synchronized with P21 via CRON.',
            'group' => 'Prophet 21 Custom Attributes',
            'position' => 4,
            'used_in_product_listing' => false,
            'user_defined' => true,
            'unique' => false,
            'required' => false,
            'backend' => '',
            'source' => '',
            'searchable' => false,
            'visible_on_front' => false,
            'filterable' => false,
            'is_used_in_grid' => false,
            'is_visible_in_grid' => false,
            'is_filterable_in_grid' => false,
            'is_filterable_in_search' => false
        ]);

        $eavSetup->addAttribute('catalog_product', 'hidden_uom_list', [
            'type' => 'text',
            'input' => 'text',
            'label' => 'UOM to hide for this product',
            'note' => 'Comma-delimited list of UOM to hide from display on this product. Not applicable if the specified
            UOM is the only UOM available on this product.',
            'group' => 'Product Details',
            'position' => 120,
            'used_in_product_listing' => false,
            'user_defined' => true,
            'unique' => false,
            'required' => false,
            'backend' => '',
            'source' => '',
            'searchable' => false,
            'visible_on_front' => false,
            'filterable' => false,
            'is_used_in_grid' => false,
            'is_visible_in_grid' => false,
            'is_filterable_in_grid' => false,
            'is_filterable_in_search' => false
        ]);
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
