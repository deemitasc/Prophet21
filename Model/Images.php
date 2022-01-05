<?php

namespace Ripen\Prophet21\Model;

use Magento\Framework\App\Filesystem\DirectoryList;

class Images extends \Ripen\Prophet21\Model\Feed
{
    const IMAGE_TYPE_WEB = 917;
    const IMAGE_TYPE_THUMBNAIL = 918;
    const IMAGE_STATUS_ACTIVE = 704;
    const IMAGE_STATUS_DELETE = 700;
    const BATCH_SIZE = 500;

    /**
     * @var \Ripen\SimpleApps\Model\Api
     */
    protected $api;

    /**
     * @var \Ripen\Prophet21\Logger\Logger
     */
    protected $logger;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Magento\Framework\Filesystem
     */
    protected $fileSystem;

    /**
     * @var \Magento\Framework\FlagManager
     */
    protected $flagManager;

    /**
     * @var \Magento\Catalog\Model\ProductRepository
     */
    protected $productRepository;

    /**
     * @var \Ripen\Prophet21\Helper\DataHelper
     */
    protected $dataHelper;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    public function __construct(
        \Ripen\SimpleApps\Model\Api $api,
        \Ripen\Prophet21\Logger\Logger $logger,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\FlagManager $flagManager,
        \Magento\Framework\Filesystem $fileSystem,
        \Magento\Framework\Filesystem\Io\File $file,
        \Magento\Catalog\Model\ProductRepository $productRepository,
        \Magento\Framework\Api\SearchCriteriaInterface $criteria,
        \Magento\Framework\Api\Search\FilterGroup $filterGroup,
        \Magento\Framework\Api\FilterBuilder $filterBuilder,
        \Magento\Catalog\Model\Product\Attribute\Source\Status $productStatus,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \Ripen\Prophet21\Helper\DataHelper $dataHelper,
        DirectoryList $directoryList,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    )
    {
        $this->api = $api;
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
        $this->flagManager = $flagManager;
        $this->fileSystem = $fileSystem;
        $this->productRepository = $productRepository;
        $this->searchCriteria = $criteria;
        $this->filterGroup = $filterGroup;
        $this->filterBuilder = $filterBuilder;
        $this->productStatus = $productStatus;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->dataHelper = $dataHelper;
        $this->file = $file;
        $this->directoryList = $directoryList;
        $this->storeManager = $storeManager;
    }

    /**
     * Generate json object with product images data based on P21 data
     * @return array
     */
    public function buildImagesDataBasedOnP21()
    {
        set_time_limit(0);

        $offset = 0;

        $data = [];
        $allImageData = $this->getModifiedSkus(10000);
        $countProducts = count($allImageData);
        $totalBatches = ceil($countProducts / self::BATCH_SIZE);

        while ($offset <= $totalBatches - 1) {
            $skus = $this->getModifiedSkus(self::BATCH_SIZE, $offset * self::BATCH_SIZE);

            $b = $offset + 1;
            $this->logger->info("Checking images for batch {$b}/{$totalBatches}");

            foreach ($skus as $sku) {
                $images = $this->api->getItemImages($sku);
                if (count($images)) {
                    $imageRecord = $this->buildImageRecordBasedOnP21($sku, $images);
                    if (!empty($imageRecord['base_image'])) {
                        $data[] = $imageRecord;
                    }
                }
            }
            $offset++;
        }

        if (count($data)) {
            $message = "Images import data has been built";
            $this->logger->info($message);
        } else {
            $message = "No images found to import";
            $this->logger->info($message);
        }

        return ['products' => $data];
    }

    /**
     * @param $sku
     * @param $images
     * @return mixed
     */
    protected function buildImageRecordBasedOnP21($sku, $images)
    {
        $imageRecord['sku'] = $sku;
        $additionalImages = '';
        $mainImageSet = false;
        foreach ($images as $image) {
            if (
                $image['link_area'] != self::IMAGE_TYPE_WEB ||
                $image['row_status_flag'] != self::IMAGE_STATUS_ACTIVE
            ) {
                continue;
            }

            $imageName = $this->fuzzyFileMatch($image['link_path']);
            if (!$imageName) {
                $this->logger->error("Item [{$sku}][{$image['link_path']}]: Image not found");
                continue;
            }

            // Set first image as a main product image
            if (!$mainImageSet) {
                $imageRecord['base_image'] = $imageName;
                $imageRecord['small_image'] = $imageName;
                $imageRecord['thumbnail_image'] = $imageName;
                $mainImageSet = true;
            } else {
                $additionalImages .= "{$imageName},";
            }
        }
        $imageRecord['additional_images'] = rtrim($additionalImages, ',');

        return $imageRecord;
    }

    /**
     * @param $imagePath
     * @return string
     */
    protected function parseImageName($imagePath)
    {
        $pieces = explode('\\', $imagePath);
        return end($pieces);
    }

    /**
     * Build images for each store
     * @return array[]
     */
    protected function buildImagesDataBasedOnDirectory()
    {
        $data = [];
        $files = $this->getFilesFromDirectory();
        $stores = $this->storeManager->getStores();
        foreach ($stores as $store) {
            $data = array_merge($data, $this->updateImages($files, $store->getCode()));
        }

        return ['products' => $data];
    }

    /**
     * @param array $files
     * @param string $storeCode
     * @return array
     */
    protected function updateImages(array $files, string $storeCode)
    {
        $data = [];
        foreach ($files as $sku => $images) {
            $imageRecord = [];
            $additionalImages = "";
            foreach ($images as $sortIndex => $image) {
                $imageRecord['sku'] = $sku;
                $imageRecord['store_view_code'] = $storeCode;

                if ($sortIndex == 1) {
                    $imageRecord['base_image'] = $image;
                    $imageRecord['small_image'] = $image;
                    $imageRecord['thumbnail_image'] = $image;
                } else {
                    $additionalImages .= "{$image},";
                }
            }
            $imageRecord['additional_images'] = rtrim($additionalImages, ',');
            $data[] = $imageRecord;
        }

        return $data;
    }

    /**
     * @return array
     */
    protected function getFilesFromDirectory()
    {
        $importDir = $this->scopeConfig->getValue('p21/feeds/images_import_directory');
        $mediaPath = $this->fileSystem->getDirectoryRead(DirectoryList::MEDIA)->getAbsolutePath();
        $directoryPath = $mediaPath . $importDir;
        $dateLastImported = $this->dataHelper->getImagesLastModifiedFilter();

        $files = $this->buildFilesData($directoryPath, $dateLastImported);

        return $files;
    }

    /**
     * @param $directoryPath
     * @param $dateLastImported
     * @return array
     */
    protected function buildFilesData($directoryPath, $dateLastImported)
    {
        $files = [];
        foreach (new \DirectoryIterator($directoryPath) as $file) {
            if (!$file->isDir()) {
                $changeTime = date("Y-m-d H:i:s", $file->getCTime());
                if ($dateLastImported <= $changeTime) {
                    $filename = current(explode('.', $file->getBasename()));
                    $namePieces = explode('_', $filename);
                    $sku = strtoupper(current($namePieces));
                    $sortOrder = count($namePieces) > 1 ? end($namePieces) : 1;
                    $files[$sku][$sortOrder] = $file->getBasename();
                }
            }
        }
        ksort($files);
        return $files;
    }

    /**
     * @return mixed
     */
    protected function useUploadedFileNames()
    {
        return $this->scopeConfig->getValue('p21/integration/use_uploaded_files_image_names');
    }

    /**
     * @return array
     * @throws \Ripen\Prophet21\Exception\P21ApiException
     */
    protected function getModifiedSkus($limit = null, $offset = null)
    {
        $skus = [];

        $dateLastImported = $this->dataHelper->getImagesLastModifiedFilter();
        if (!$dateLastImported) {
            return $this->getProductSkus($limit, $offset);
        }

        /**
         * Returned result is sorted by modification date in descending order.
         * The logic below relies on that type of sorting.
         */
        $images = $this->api->getAllImages($limit, $offset);
        foreach ($images as $image) {
            try {
                // Check if product exists.
                // TODO: Use more efficient approach for this.
                $this->productRepository->get($image['item_id']);

                $skus[] = $image['item_id'];

                // This assumes that api result is always sorted in descending order
                if ($image['date_last_modified'] <= $dateLastImported) {
                    break;
                }
            } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                continue;
            }
        }
        return $skus;
    }

    /**
     * @param $fileLink
     * @return bool|string
     */
    protected function fuzzyFileMatch($fileLink)
    {
        $importDir = $this->scopeConfig->getValue('p21/feeds/images_import_directory');
        $requestedFile = $importDir . '/' . $this->parseImageName($fileLink);
        $mediaPath = $this->directoryList->getPath(DirectoryList::MEDIA);

        $requestedFullPath = $mediaPath . '/' . $requestedFile;

        if (file_exists($requestedFullPath)) {
            return $requestedFile;
        }

        $filesArray = glob(dirname($requestedFullPath) . '/*');

        // Ignore case and search for a first matching file name
        foreach ($filesArray as $foundFile) {
            $foundFileParts = pathinfo($foundFile);
            if (strtolower($foundFile) == strtolower($requestedFullPath)) {
                return $importDir . '/' . $foundFileParts['basename'];
            }
        }

        // Ignore extension and search for a first matching file name
        $requestedFileParts = pathinfo($requestedFullPath);
        foreach ($filesArray as $foundFile) {
            $foundFileParts = pathinfo($foundFile);
            if (strtolower($requestedFileParts['filename']) == strtolower($foundFileParts['filename'])) {
                return $importDir . '/' . $foundFileParts['basename'];
            }
        }

        return false;
    }

    /**
     *
     * For initial load, ignore modified_date flag and use
     * product collection to get all visible skus
     *
     * @param null $limit
     * @param null $offset
     * @return array
     */
    protected function getProductSkus($limit = null, $offset = null)
    {
        /**
         * If specific skus are set on admin for debugging, use them
         * Admin > Services > Prophet 21
         */
        $individualProductsList = $this->dataHelper->getIndividualProductsImport();
        $skus = explode(',', $individualProductsList);

        /**
         * Also account for skus being excluded
         */
        $productsToExclude = $this->dataHelper->getProductsImportExclusions();
        $productsToExclude = explode(',', $productsToExclude);

        if ($individualProductsList && count($skus)) {
            if (!empty($productsToExclude)) {
                $skus = array_diff($skus, $productsToExclude);
            }
            return $skus;
        }

        $skus = [];
        $page = ($offset / $limit) + 1;
        $productItems = $this->productCollectionFactory->create();
        $productItems->addAttributeToFilter('status', ['in' => $this->productStatus->getVisibleStatusIds()]);

        if (!empty($productsToExclude)) {
            $productItems->addAttributeToFilter('sku', ['nin' => $productsToExclude]);
        }

        $productItems->setPageSize($limit);
        $productItems->setCurPage($page);

        foreach ($productItems as $productItem) {
            $skus[] = $productItem->getSku();
        }

        return $skus;
    }

    /**
     * Generate CSV file with product images data
     *
     * @return array
     * @api
     */
    public function generateFile()
    {
        try {
            set_time_limit(0);
            $this->logger->info("Generate images data file");

            $directory = $this->getImportDir();
            $file = $directory . '/images.csv';
            $historicalFile = $directory . '/images-' . date('Ymd-Hi') . '.csv';

            $this->file->cp($file, $historicalFile);

            $handle = fopen($file, 'w');

            if ($this->useUploadedFileNames()) {
                $productsData = $this->buildImagesDataBasedOnDirectory();
            } else {
                $productsData = $this->buildImagesDataBasedOnP21();
            }

            $productIndex = 0;
            foreach ($productsData['products'] as $product) {
                if ($productIndex == 0) {
                    fputcsv($handle, array_keys($product));
                }
                fputcsv($handle, $product);
                $productIndex++;
            }

            if (filesize($file)) {
                $this->logger->info("Images import file is successfully created");
            } else {
                $this->logger->info("File doesn't exist or empty.");
            }
        } catch (\Throwable $e) {
            $this->logger->error($e);
        }
    }
}
