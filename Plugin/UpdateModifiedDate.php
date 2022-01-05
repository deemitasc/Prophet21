<?php
/**
 * Updates modified date after product import with Firebear module
 * Plugin for Firebear\ImportExport\Model\Import.php
 */

namespace Ripen\Prophet21\Plugin;

class UpdateModifiedDate
{
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Ripen\Prophet21\Helper\DataHelper
     */
    protected $dataHelper;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Ripen\Prophet21\Helper\DataHelper $dataHelper
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->dataHelper = $dataHelper;
    }

    public function afterImportSourcePart(
        \Firebear\ImportExport\Model\Import $subject,
        $result,
        $file,
        $offset,
        $job,
        $show
    ) {
        if ($result) {
            $newDate = date('Y-m-d H:i:s');
            if ($job == $this->scopeConfig->getValue('p21/feeds/firebear_import_products_job_id')) {
                $this->dataHelper->setProductsLastImported($newDate);
            }
            if ($job == $this->scopeConfig->getValue('p21/feeds/firebear_import_images_job_id')) {
                $this->dataHelper->setImagesLastImported($newDate);
            }
        }
        return $result;
    }
}
