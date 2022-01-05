<?php

namespace Ripen\Prophet21\Model;

class Inventory extends \Ripen\Prophet21\Model\Feed
{
    const BATCH_SIZE = 500;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Magento\Framework\App\Response\Http\FileFactory
     */
    protected $fileFactory;

    /**
     * @var \Magento\Framework\Filesystem\DirectoryList
     */
    protected $dir;

    /**
     * @var \Ripen\SimpleApps\Model\Api
     */
    protected $api;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Ripen\Prophet21\Helper\MultistoreHelper
     */
    protected $multistoreHelper;

    /**
     * @var \Ripen\Prophet21\Helper\DataHelper
     */
    protected $dataHelper;

    /**
     * @var \Magento\Framework\DB\Adapter\AdapterInterface
     */
    protected $connection;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\App\Response\Http\FileFactory $fileFactory,
        \Magento\Framework\Filesystem\DirectoryList $dir,
        \Ripen\SimpleApps\Model\Api $api,
        \Ripen\Prophet21\Logger\Logger $logger,
        \Ripen\Prophet21\Helper\MultistoreHelper $multistoreHelper,
        \Magento\Framework\Filesystem\Io\File $io,
        \Ripen\Prophet21\Helper\DataHelper $dataHelper,
        \Magento\Framework\App\ResourceConnection $resourceConnection
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->fileFactory = $fileFactory;
        $this->dir = $dir;
        $this->api = $api;
        $this->logger = $logger;
        $this->multistoreHelper = $multistoreHelper;
        $this->dataHelper = $dataHelper;
        $this->io = $io;
        $this->connection = $resourceConnection->getConnection();

        parent::__construct($io, $scopeConfig);
    }

    /**
     * @api
     * @return string[]
     * @throws \Ripen\Prophet21\Exception\P21ApiException
     */
    public function generateFile()
    {
        try {
            set_time_limit(0);
            $this->logger->info("Generate inventory file");

            $tableName = $this->connection->getTableName('catalog_product_entity');
            $select = $this->connection->select()->from($tableName, 'sku');
            $allMagentoSkus = $this->connection->fetchCol($select);

            $directory = $this->getImportDir();
            $file = $directory.'/inventory.csv';
            $historicalFile = $directory.'/inventory-'. date('Ymd-Hi') .'.csv';

            $filePath = $this->io->cp($file, $historicalFile);

            $handle = fopen($file, 'w');

            $offset = 0;
            $data = [];
            $batchIndex = 0;
            $productIndex = 0;
            do {

                $this->logger->info("Get products via API for batch {$batchIndex}");
                $offset = $batchIndex * self::BATCH_SIZE;

                $products = $this->api->getProducts(
                    self::BATCH_SIZE,
                    $offset,
                    false,
                    $this->dataHelper->getOnlineOnlyFlag(),
                    [
                        'itemsStock',
                        'itemsLocations'
                    ],
                    $this->dataHelper->getIndividualProductsImport(),
                    $this->dataHelper->getProductsLastModifiedFilter()
                );

                $productsToExclude = $this->dataHelper->getProductsImportExclusions();
                $productsToExclude = explode(',', $productsToExclude);

                foreach($products['data'] as $product) {

                    // skip products excluded in admin config
                    // skip products that are not in Magento
                    if (!in_array($product['item_id'], $productsToExclude) &&
                        in_array($product['item_id'], $allMagentoSkus)
                    ) {

                        // Use itemsStock data piece from the API response to get product's stock info
                        // If itemStock is empty, fall back to itemsLocations.

                        $itemStockLocations = $product['resources']['itemsStock'];
                        if (!count($itemStockLocations)) {
                            $itemStockLocations = $product['resources']['itemsLocations'];
                        }

                        foreach ($itemStockLocations as $location) {

                            $data['source_code'] = intval($location['location_id']);
                            $data['sku'] = $product['item_id'];
                            $qty = $this->api->calculateLocationNetStock($location);
                            $data['status'] = 1;
                            $data['quantity'] = $qty;

                            if ($productIndex == 0) {
                                fputcsv($handle, array_keys($data));
                            }
                            fputcsv($handle, $data);
                            $productIndex++;
                        }
                    }
                }
                $batchIndex++;
            } while (count($products['data']) >= self::BATCH_SIZE);

            if(filesize($file)) {
                $this->logger->info("Inventory import file is successfully created");
            } else {
                $this->logger->info("Something went wrong.");
            }

        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
        }

    }
}
