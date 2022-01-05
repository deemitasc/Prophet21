<?php

namespace Ripen\Prophet21\Helper;

class DataHelper extends \Magento\Framework\App\Helper\AbstractHelper
{
    const DATE_PRODUCTS_LAST_IMPORTED_FLAG_CODE = 'products_date_last_imported';
    const DATE_IMAGES_LAST_IMPORTED_FLAG_CODE = 'images_date_last_imported';

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Magento\Framework\FlagManager
     */
    protected $flagManager;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\FlagManager $flagManager
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->flagManager = $flagManager;
    }

    /**
     * @return string
     */
    public function getIndividualProductsImport()
    {
        $itemList = $this->scopeConfig->getValue('p21/debug/individual_products_import');
        return preg_replace('/\s+/', '', $itemList);
    }
    /**
     * @return string
     */
    public function getProductsImportExclusions()
    {
        $itemList = $this->scopeConfig->getValue('p21/debug/products_import_exclusions');
        return preg_replace('/\s+/', '', $itemList);
    }

    /**
     * @return int
     */
    public function getOnlineOnlyFlag()
    {
        return (int) $this->scopeConfig->getValue('p21/debug/online_only_flag');
    }

    /**
     * @param string $newDate
     * @return void
     */
    public function setProductsLastImported($newDate)
    {
        $this->flagManager->saveFlag(self::DATE_PRODUCTS_LAST_IMPORTED_FLAG_CODE, $newDate);
    }

    /**
     * @return string|null
     */
    public function getProductsLastModifiedFilter()
    {
        if (! $this->scopeConfig->getValue('p21/debug/load_all_products')) {
            return $this->flagManager->getFlagData(self::DATE_PRODUCTS_LAST_IMPORTED_FLAG_CODE) ?: null;
        }
        return null;
    }

    /**
     * @param string $newDate
     * @return void
     */
    public function setImagesLastImported($newDate)
    {
        $this->flagManager->saveFlag(self::DATE_IMAGES_LAST_IMPORTED_FLAG_CODE, $newDate);
    }

    /**
     * @return string|null
     */
    public function getImagesLastModifiedFilter()
    {
        if (! $this->scopeConfig->getValue('p21/debug/load_all_images')) {
            return $this->flagManager->getFlagData(self::DATE_IMAGES_LAST_IMPORTED_FLAG_CODE) ?: null;
        }
        return null;
    }

    /**
     * @return string|null
     */
    public function getP21BillToPaymentMethods()
    {
        if ($this->scopeConfig->getValue('p21/integration/billing_address_payment_methods')) {
           return $this->scopeConfig->getValue('p21/integration/billing_address_payment_methods');
        }

        return null;
    }
}
