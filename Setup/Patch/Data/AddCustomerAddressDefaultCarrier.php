<?php

namespace Ripen\Prophet21\Setup\Patch\Data;

use Magento\Customer\Api\AddressMetadataInterface;
use Magento\Eav\Model\Config;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class AddCustomerAddressDefaultCarrier implements DataPatchInterface
{
    /**
     * @var Config
     */
    private $eavConfig;

    /**
     * @var EavSetupFactory
     */
    private $eavSetupFactory;

    /**
     * AddressAttribute constructor.
     *
     * @param Config              $eavConfig
     * @param EavSetupFactory     $eavSetupFactory
     */
    public function __construct(
        Config $eavConfig,
        EavSetupFactory $eavSetupFactory
    ) {
        $this->eavConfig = $eavConfig;
        $this->eavSetupFactory = $eavSetupFactory;
    }

    /**
     * Add ship-to ID to customer address. This attribute is not originally intended to actually be populated
     * on normally saved addresses; it simply makes Magento recognize it as a valid address field so
     * that we can include it in addresses loaded via API and dynamically inject it.
     *
     * {@inheritdoc}
     */
    public function apply()
    {
        /** @var \Magento\Eav\Setup\EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create();

        $eavSetup->addAttribute(AddressMetadataInterface::ENTITY_TYPE_ADDRESS, 'p21_default_carrier_id', [
            'type'             => 'int',
            'label'            => 'P21 Default Carrier ID',
            'visible'          => true,  // must be true in order to pass to JS
            'required'         => false,
            'user_defined'     => true,
            'system'           => false,
            'group'            => 'General',
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases(): array
    {
        return [];
    }
}
