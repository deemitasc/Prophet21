<?php

namespace Ripen\Prophet21\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Ripen\Prophet21\Helper\MultistoreHelper;

class AddP21StoreClassMapping implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    protected $moduleDataSetup;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var WriterInterface
     */
    protected $configWriter;

    /**
     * @var MultistoreHelper
     */
    protected $multistoreHelper;

    /**
     * @param \Magento\Framework\Setup\ModuleDataSetupInterface $moduleDataSetup
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\App\Config\Storage\WriterInterface $configWriter
     * @param \Ripen\Prophet21\Helper\MultistoreHelper $multistoreHelper
     */
    public function __construct(
        \Magento\Framework\Setup\ModuleDataSetupInterface $moduleDataSetup,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\App\Config\Storage\WriterInterface $configWriter,
        \Ripen\Prophet21\Helper\MultistoreHelper $multistoreHelper
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->scopeConfig = $scopeConfig;
        $this->configWriter = $configWriter;
        $this->multistoreHelper = $multistoreHelper;
    }

    /**
     * Takes the hardcoded store class ids and adds them to the config path p21/integration/store_ids_mapping
     *
     * {@inheritdoc}
     */
    public function apply()
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $storeIdsMappingPath = 'p21/integration/store_ids_mapping';
        $scopeConfig = $this->scopeConfig->getValue($storeIdsMappingPath);
        $wholesaleCode = $this->multistoreHelper->getWholesaleWebsiteCode();
        $retailCode = $this->multistoreHelper->getRetailWebsiteCode();

        $defaultMappingConfig = [];
        if($wholesaleCode) {
            $defaultMappingConfig[5] = $wholesaleCode;
        }
        if($retailCode) {
            $defaultMappingConfig[6] = $retailCode;
        }
        if($wholesaleCode && $retailCode) {
            $defaultMappingConfig[7] = [$wholesaleCode, $retailCode];
        }

        if(!$scopeConfig) {
            $this->configWriter->save(
                $storeIdsMappingPath,
                json_encode($defaultMappingConfig)
            );
        }

        $this->moduleDataSetup->getConnection()->endSetup();
    }

    public static function getDependencies()
    {
        return [];
    }

    public function getAliases()
    {
        return [];
    }
}
