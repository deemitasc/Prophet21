<?php
namespace Ripen\Prophet21\Model;

use Magento\Catalog\Model\Product\Visibility;
use Ripen\Prophet21\Exception\P21ApiException;

class Products extends \Ripen\Prophet21\Model\Feed implements \Ripen\Prophet21\Api\CustomerPricesInterface
{
    const BATCH_SIZE = 100;

    const P21_ATTRIBUTES = [
        'class_id2',
        'class_id3',
        'class_id4',
        'class_id5',
        'length',
        'width',
        'height',
        'alternate_code',
        'keywords',
        'base_unit',
        'default_selling_unit',
        'date_last_modified',
        'haz_mat_flag',
        'warranty_days',
        'short_code'
    ];

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
     * @var \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory
     */
    protected $productCollectionFactory;

    /**
     * @var \Magento\Catalog\Model\ProductFactory
     */
    protected $productFactory;

    /**
     * @var \Magento\Catalog\Model\Product\Visibility
     */
    protected $productVisibility;

    /**
     * @var \Magento\Catalog\Model\Product\Attribute\Source\Status
     */
    protected $productStatus;

    /**
     * @var \Ripen\Prophet21\Helper\Customer
     */
    protected $customerHelper;

    /**
     * @var \Ripen\Prophet21\Helper\CatalogRule
     */
    protected $catalogRuleHelper;

    /**
     * @var \Ripen\Prophet21\Helper\DataHelper
     */
    protected $dataHelper;

    /**
     * Products constructor.
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\App\Response\Http\FileFactory $fileFactory
     * @param \Magento\Framework\Filesystem\DirectoryList $dir
     * @param \Ripen\SimpleApps\Model\Api $api
     * @param \Ripen\Prophet21\Logger\Logger $logger
     * @param \Ripen\Prophet21\Helper\MultistoreHelper $multistoreHelper
     * @param \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory
     * @param \Magento\Catalog\Model\ProductFactory $productFactory
     * @param Visibility $productVisibility
     * @param \Magento\Catalog\Model\Product\Attribute\Source\Status $productStatus
     * @param \Ripen\Prophet21\Helper\Customer $customerHelper
     * @param \Ripen\Prophet21\Helper\CatalogRule $catalogRuleHelper
     * @param \Magento\Catalog\Api\ProductRepositoryInterface $productRepositoryInterface
     * @param \Magento\Indexer\Model\IndexerFactory $indexerFactory
     * @param \Ripen\Prophet21\Helper\DataHelper $dataHelper
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\App\Response\Http\FileFactory $fileFactory,
        \Magento\Framework\Filesystem\DirectoryList $dir,
        \Ripen\SimpleApps\Model\Api $api,
        \Ripen\Prophet21\Logger\Logger $logger,
        \Ripen\Prophet21\Helper\MultistoreHelper $multistoreHelper,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Catalog\Model\Product\Visibility $productVisibility,
        \Magento\Catalog\Model\Product\Attribute\Source\Status $productStatus,
        \Ripen\Prophet21\Helper\Customer $customerHelper,
        \Ripen\Prophet21\Helper\CatalogRule $catalogRuleHelper,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepositoryInterface,
        \Magento\Indexer\Model\IndexerFactory $indexerFactory,
        \Ripen\Prophet21\Helper\DataHelper $dataHelper,
        \Magento\Framework\Filesystem\Io\File $io,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\UrlRewrite\Model\ResourceModel\UrlRewriteCollectionFactory $urlRewriteCollectionFactory
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->fileFactory = $fileFactory;
        $this->dir = $dir;
        $this->api = $api;
        $this->logger = $logger;
        $this->multistoreHelper = $multistoreHelper;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->productFactory = $productFactory;
        $this->productVisibility = $productVisibility;
        $this->productStatus = $productStatus;
        $this->customerHelper = $customerHelper;
        $this->catalogRuleHelper = $catalogRuleHelper;
        $this->productRepositoryInterface = $productRepositoryInterface;
        $this->indexerFactory = $indexerFactory;
        $this->dataHelper = $dataHelper;
        $this->io = $io;
        $this->connection = $resourceConnection->getConnection();
        $this->urlRewriteCollectionFactory = $urlRewriteCollectionFactory;
    }

    /**
     * Get data for products from P21, formatted for Firebear Import.
     *
     * @api
     * @return array
     */
    public function products()
    {
        set_time_limit(0);

        try {
            $retailCustomerId = $this->customerHelper->getRetailP21CustomerId();

            $offset = 0;
            $data = [];

            $excludedAttributes = $this->getExcludedAttributes();
            $UOMsToAllowDecimalQty = $this->getUOMsToAllowDecimalQty();

            $tableName = $this->connection->getTableName('catalog_product_entity');
            $select = $this->connection->select()->from($tableName, 'sku');
            $allMagentoSkus = $this->connection->fetchCol($select);

            $productsToExclude = $this->dataHelper->getProductsImportExclusions();
            $productsToExclude = explode(',', $productsToExclude);

            $batchIndex = 0;
            do {
                $offset = $batchIndex * self::BATCH_SIZE;
                $this->logger->info("Get products via API [{$offset}][" . self::BATCH_SIZE . "]");

                $products = $this->api->getProducts(
                    self::BATCH_SIZE,
                    $offset,
                    false,
                    $this->dataHelper->getOnlineOnlyFlag(),
                    [
                        'itemsAlternatecodes',
                        'itemsAccessories',
                        'itemsStock'
                    ],
                    $this->dataHelper->getIndividualProductsImport(),
                    $this->dataHelper->getProductsLastModifiedFilter()
                );

                $productsFoundCount = count($products['data']);

                // filter out excluded products from the API results
                if (! empty($productsToExclude)) {
                    $products['data'] = array_filter($products['data'], function ($v) use ($productsToExclude) {
                        return (! in_array($v['item_id'], $productsToExclude));
                    });
                }

                $skus = array_column($products['data'], 'item_id');

                $this->logger->info("Get prices via API");
                $productPrices = $this->api->getCustomerPriceData($retailCustomerId, $skus);

                foreach ($products['data'] as $product) {
                    $record = [];
                    $productOnline = 0;

                    $availableInWebsites = $this->formatWebsites($product['class_id5']);
                    if ($availableInWebsites) {
                        $productOnline = 1;
                    }

                    $isProductAlreadyInMagento = in_array($product['item_id'], $allMagentoSkus);
                    if (!$isProductAlreadyInMagento && !$this->scopeConfig->getValue('p21/integration/enable_new_product_import')) {
                        $this->logger->info("Skip product [{$product['item_id']}]. Creation of new products is disabled.");
                        continue;
                    }

                    $record['sku'] = $product['item_id'];
                    $record['attribute_set_code'] = 'Default';
                    $record['product_type'] = 'simple';
                    $record['categories'] = 'Default Category/Shop';
                    $record['product_websites'] = $availableInWebsites;
                    $record['name'] = $product['item_desc'];
                    $record['short_description'] = $product['extended_desc'];
                    $record['weight'] = max(1, (float)$product['weight']);
                    $record['product_online'] = $productOnline;
                    $record['tax_class_name'] = 'Taxable Goods';

                    if (!empty($productPrices[$product['item_id']])) {
                        $skuPriceData = $productPrices[$product['item_id']];
                        $productUnitPrice = $this->getRetailUnitPrice($skuPriceData);
                        if (!$productUnitPrice && !$this->scopeConfig->getValue('p21/integration/allow_import_for_products_without_price')) {
                            $message = "No price set for sku [{$product['item_id']}]";
                            $this->logger->error($message);
                            continue;
                        }
                        $record['price'] = $productUnitPrice;
                        $record['tier_prices'] = $this->formatTierPrices($skuPriceData);
                    } else {
                        $message = "No prices found for sku [{$product['item_id']}]";
                        $this->logger->error($message);
                        continue;
                    }

                    $record['meta_keywords'] = $product['keywords'];
                    $record['created_at'] = $product['date_created'];
                    $record['display_product_options_in'] = 'Block after Info Column';
                    $record['msrp_display_actual_price_type'] = 'Use config';
                    $record['additional_attributes'] = $this->formatAdditionalAttributes($product);

                    //$qty = $this->api->calculateNetStock($product['resources']['itemsStock']);
                    /**
                     * This value is needed by single source inventory, which is not used for this site.
                     * However, when qty is not provided or set to a real qty value, stock quantity appears
                     * doubled on website pages. It happens because a record for "default" source is added
                     * to the inventory_source_item table quantity set to total quantity across all sources.
                     */
                    $qty = 0;
                    $record['is_in_stock'] = $qty ? 1 : 0;
                    $record['qty'] = $qty;

                    $record['out_of_stock_qty'] = 0;
                    $record['use_config_min_qty'] = 1;

                    /**
                     * Default product to not use Qty uses Decimals, but allow config overrides
                     */
                    $record['is_qty_decimal'] = 0;
                    if (in_array($product['default_selling_unit'], $UOMsToAllowDecimalQty)) {
                        $record['is_qty_decimal'] = 1;
                    }

                    $record['allow_backorders'] = 1;
                    $record['use_config_backorders'] = 1;
                    $record['min_cart_qty'] = 1;
                    $record['use_config_min_sale_qty'] = 0;
                    $record['max_cart_qty'] = 0;
                    $record['use_config_max_sale_qty'] = 1;
                    $record['use_config_notify_stock_qty'] = 1;
                    $record['manage_stock'] = 1;
                    $record['use_config_manage_stock'] = 1;
                    $record['use_config_qty_increments'] = 1;
                    $record['qty_increments'] = 0;
                    $record['use_config_enable_qty_inc'] = 1;
                    $record['enable_qty_increments'] = 0;
                    $record['is_decimal_divided'] = 0;
                    $record['deferred_stock_update'] = 0;
                    $record['use_config_deferred_stock_update'] = 1;
                    $record['related_skus'] = $this->formatAccessories($product['resources']['itemsAccessories']);

                    foreach ($excludedAttributes as $excludedAttributeName) {
                        unset($record[$excludedAttributeName]);
                    }

                    $data[] = $record;
                }
                $batchIndex++;
            } while ($productsFoundCount >= self::BATCH_SIZE);

            if (count($data)) {
                $message = "Product import data is successfully built";
                $this->logger->info($message);
            } else {
                $message = "No products found to import";
                $this->logger->info($message);
            }
        } catch (\Throwable $e) {
            $this->logger->error($e);
        }

        return ['products' => $data];
    }

    public function sanitizeDeletedProducts()
    {
        // Note: per Stuart, there is no such thing as "hard deleted" products. These are actually just
        // cases where an item ID has changed, where it looks like to us that the old item disappeared.
        // This will be resolved by ENG-101. Any time that a product is explicitly deleted in P21, it stays
        // but with the `delete_flag` attribute set to "Y."
        $this->logger->info('Sanitize hard deleted and disabled products');

        $magentoProducts = $this->productCollectionFactory->create();
        $magentoProducts->addAttributeToFilter('status', ['in' => $this->productStatus->getVisibleStatusIds()]);
        $magentoProducts->addAttributeToFilter('type_id', ['neq' => 'configurable']);

        $itemListString = $this->dataHelper->getIndividualProductsImport();
        $individualProductsList = [];
        if ($itemListString) {
            $individualProductsList = explode(',', $itemListString);
            $magentoProducts->addAttributeToFilter('sku', ['in' => $individualProductsList]);
        }

        // Build a list of all enabled Magento skus
        $magentoSkus = [];
        foreach ($magentoProducts as $magentoProduct) {
            $magentoSkus[] = $magentoProduct->getSku();
        }

        $batchIndex = 0;
        $skusToDisable = [];

        // Get P21 enabled products and match them against magento skus
        // Build a list of magento products to disable
        do {
            $limit = self::BATCH_SIZE;
            $offset = $batchIndex * $limit;

            $itemList = array_slice($magentoSkus, $offset, $limit);
            $itemListString = implode(',', $itemList);

            $this->logger->info("Get online products via API [{$offset}][{$limit}]");
            $products = $this->api->getProducts(
                $limit,
                0,
                false,
                $this->dataHelper->getOnlineOnlyFlag(),
                null,
                $itemListString
            );

            $p21OnlineSkus = array_column($products['data'], 'item_id');
            $skusDisabledInBatch = array_diff($itemList, $p21OnlineSkus);
            $skusToDisable = array_merge($skusToDisable, $skusDisabledInBatch);
            $batchIndex++;
        } while (count($itemList) >= $limit);

        // products excluded from import should always be disabled
        $skusToDisable = array_merge($skusToDisable, explode(',', $this->dataHelper->getProductsImportExclusions()));

        $this->logger->info("P21 products to disable [" . implode(',', $skusToDisable) . "]");

        // Disable Magento products that are disabled or not found in P21
        $count = 0;
        foreach ($skusToDisable as $sku) {
            $this->logger->info("Disabling sku [{$sku}]");

            try {
                $product = $this->productRepositoryInterface->get($sku);
                $count++;

                $product->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_DISABLED);
                $product->getResource()->saveAttribute($product, 'status');

                // Delete url rewrites for all disabled products.
                // They will be recreated if products are enabled back
                $urlRewrites = $this->urlRewriteCollectionFactory->create();
                $urlRewrites->addFieldToFilter('entity_id', ['eq' => $product->getId()]);
                $urlRewrites->addFieldToFilter('entity_type', ['eq' => 'product']);
                foreach ($urlRewrites as $urlRewrite) {
                    $urlRewrite->delete();
                }
            } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                $this->logger->debug("SKU [{$sku}] not found, skipping.");
            }
        }
        $this->logger->info("Disabled [{$count}] products");

        if ($count) {
            $this->reindex();
        }
    }

    /**
     * @throws \Throwable
     */
    protected function reindex()
    {
        $indexerIds = ['catalog_category_product', 'catalog_product_attribute', 'catalogsearch_fulltext'];
        foreach ($indexerIds as $indexerId) {
            $indexer = $this->indexerFactory->create()->load($indexerId);
            $indexer->reindexAll();
            $this->logger->info("Reindex [{$indexerId}] completed");
        }
    }

    /**
     * Get customer-specific pricing from P21 given specific customer IDs.
     *
     * @param int $customerId Magento customer ID
     * @param array|string $skus
     * @param array|string $quantities
     * @return array
     * @throws P21ApiException
     */
    public function productCustomerPrices($customerId, $skus, $quantities = null)
    {
        if (is_string($skus)) {
            $skus = json_decode(base64_decode($skus), true);

            // Unable to parse skus as JSON, silently fail and log JSON error
            if (JSON_ERROR_NONE !== json_last_error()) {
                $this->logger->error("Unable to parse skus as JSON - " . json_last_error_msg());
                throw new \UnexpectedValueException('Invalid price query.');
            }
        }

        try {
            if (empty($skus) || empty($customerId)) {
                return [];
            }

            // fetch P21 customer code for customer $customerId
            $p21CustomerId = $this->customerHelper->getP21CustomerIdByMagentoCustomerId((int) $customerId);

            $rawPriceData = $this->api->getCustomerPriceData($p21CustomerId, $skus, $quantities);
            // TODO: Log warning for any prices that were queried but are missing in API response.

            $priceData = [];
            foreach ($rawPriceData as $sku => $row) {
                // only include SKUs that were found
                if (empty($row['inv_mast_uid'])) {
                    continue;
                }

                try {
                    $unitPrice = $this->getWholesaleUnitPrice($row);
                    $price = $oldPrice = round($unitPrice, 2);
                    $price = $this->applyOnlineOrderDiscount($price);
                    $tierPrices = [];  // default value needed to exist at minimum as to not break the frontend

                    // only check for tier pricing if the price is not contracted, as Contract Price > Tier Price > Library Price
                    if (!$this->wholesaleUnitPriceIsContracted($row)) {
                        // reindex array to ensure it is treated as an array in JSON
                        $tierPrices = array_values($this->getWholesaleTierPrices($row));
                    }

                    $priceData[] = [
                        'sku' => $sku,
                        'unitPrice' => $price,
                        'oldPrice' => $oldPrice,
                        'tierPrices' => $tierPrices,
                    ];
                } catch (\Ripen\Prophet21\Exception\CustomerSpecificPriceMissingException $e) {
                    // no customer specific price found for this sku, log and skip
                    $this->logger->debug("Customer Specific Price Missing for Customer {$p21CustomerId} on Product {$sku}");
                    continue;
                }
            }
            return $priceData;
        }
        // There was a problem in getting the corresponding P21 customer code or connecting to the API.
        catch (\Throwable $e) {
            $errorMessage = "Problem getting corresponding P21 customer code or connecting to the API: " . $e;
            $this->logger->error($errorMessage);
            throw new P21ApiException($errorMessage);
        }
    }

    /**
     * The main retail pricing of products, used for product import feed
     *
     * @param $priceData
     * @return mixed
     */
    protected function getRetailUnitPrice($priceData)
    {
        $useLibraryPricing = $this->scopeConfig->getValue('p21/integration/use_library_pricing_for_retail_customer');
        $priceFieldToUse = $this->scopeConfig->getValue('p21/integration/default_price_field');
        return $useLibraryPricing ? $priceData['libraryPrice']['unit_price'] : $priceData['listPrice'][$priceFieldToUse];
    }

    /**
     * The customer-specific special pricing, must be used in conjunction with methods that
     * fetch prices associated to customers' P21 customer code as this method does not default to the retail listPrice
     *
     * @param $priceData
     * @return mixed
     * @throws \Ripen\Prophet21\Exception\CustomerSpecificPriceMissingException
     */
    protected function getWholesaleUnitPrice($priceData)
    {
        $unitPrice = $this->wholesaleUnitPriceIsContracted($priceData)
            ? $priceData['contractPrice']['unit_price']
            : $priceData['libraryPrice']['unit_price'];

        if (empty($unitPrice)) {
            throw new \Ripen\Prophet21\Exception\CustomerSpecificPriceMissingException(__('No customer specific price found.'));
        }

        return  $unitPrice;
    }

    /**
     * @param $priceData
     * @return bool
     */
    protected function wholesaleUnitPriceIsContracted($priceData)
    {
        // Note: contract_no will be null if contract has expired
        if ((! empty($priceData['contractPrice']['unit_price'])) && (! empty($priceData['contractPrice']['contract_no']))) {
            return true;
        }
        return false;
    }

    /**
     * @param array $priceData
     * @return string
     */
    protected function formatTierPrices($priceData)
    {
        /**
         * Format: CustomerGroup,Qty,Price,Percent,Website|NextTier
         * Example: General,10,23.45,0,All|General,20,21.45,0,All
         */

        $prices = '';
        $priceBreaks = $priceData['priceBreaks'];

        if (!is_array($priceBreaks)) {
            return '';
        }

        $priceBreaks = $this->sanitizePriceBreaks($priceBreaks);

        foreach ($priceBreaks as $priceBreak) {
            $prices .= "ALL GROUPS," . $priceBreak['break'] . "," . $priceBreak['unit_price'] . ",0,All|";
        }

        return rtrim($prices, '|');
    }

    /**
     * Parses through returned API data and attempt to aggregate/unify tier prices (price breaks), as they may appear
     * in different places with different item SKUs. Then apply wholesale online order discount.
     *
     * @param array $priceData
     * @return array
     */
    protected function getWholesaleTierPrices($priceData)
    {
        if (isset($priceData['priceBreaks'])) {
            $tiers = $this->sanitizePriceBreaks($priceData['priceBreaks']);
        }

        // If there were no legitimate standard breaks, check libraryPrice and simulate.
        if (empty($tiers) && ! empty($priceData['libraryPrice']['next_calculation_value'])) {
            $tiers = $this->sanitizePriceBreaks([
                [
                    'break' => $priceData['libraryPrice']['next_break'],
                    'unit_price' => $priceData['libraryPrice']['next_calculation_value'],
                ]
            ]);
        }

        if (empty($tiers)) {
            return [];
        }

        // Round to two decimal places and apply online order discount.
        foreach ($tiers as &$tier) {
            $price = round($tier['unit_price'], 2);
            $tier['price'] = $this->applyOnlineOrderDiscount($price);
        }
        return $tiers;
    }

    /**
     * @param array $tiers
     * @return array
     */
    protected function sanitizePriceBreaks($tiers)
    {
        if (! is_array($tiers)) {
            return [];
        }

        // Include only tiers with valid breaks.
        $tiers = array_filter($tiers, function ($tier) {
            return $tier['break'] > 1 && $tier['break'] < 999999999;
        });

        // Include only tiers with valid unit prices.
        $tiers = array_filter($tiers, function ($tier) {
            return ! empty($tier['unit_price']);
        });

        return $tiers;
    }

    /**
     * @param float $price
     * @return float
     */
    protected function applyOnlineOrderDiscount($price)
    {
        $discountRule = $this->getOnlineOrderDiscountRule();
        if (! is_null($discountRule)) {
            $price = $price - $price * ($discountRule->getDiscountAmount() / 100);
        }

        return $price;
    }

    /**
     * @return \Magento\CatalogRule\Api\Data\RuleInterface|null
     */
    protected function getOnlineOrderDiscountRule()
    {
        $sku = $this->scopeConfig->getValue('p21/feeds/wholesale_discount_rule_sku');
        $websiteId = $this->multistoreHelper->getCurrentWebsiteId();

        return $this->catalogRuleHelper->getRuleBySkuAndWebsiteId($sku, $websiteId);
    }

    protected function getExcludedAttributes()
    {
        $attributes = $this->scopeConfig->getValue('p21/integration/excluded_attribute_names');
        $attributes = explode(',', $attributes);
        $attributes = array_map('trim', $attributes);

        return $attributes;
    }

    /**
     * @return array
     */
    protected function getUOMsToAllowDecimalQty()
    {
        $attributes = $this->scopeConfig->getValue('p21/integration/uoms_to_allow_decimal_qty');
        $attributes = explode(',', $attributes);
        $attributes = array_map('trim', $attributes);

        return $attributes;
    }

    /**
     * @param string $class5Code
     * @return string
     */
    public function formatWebsites($class5Code)
    {
        $websites = [];
        $storeIds = $this->scopeConfig->getValue('p21/integration/store_ids_mapping');
        $mappedIds = json_decode($storeIds, true) ?: [];

        if (isset($mappedIds[$class5Code])) {
            $websites = $mappedIds[$class5Code];
        }

        // Convert mapping value to an array if single-value string shorthand was used.
        if (is_string($websites)) {
            $websites = [$websites];
        }

        return implode(',', $websites);
    }

    /**
     * @param $itemsAccessories
     * @return string
     */
    public function formatAccessories($itemsAccessories)
    {
        $accessories = implode(',', array_column($itemsAccessories, 'item_id'));
        return $accessories;
    }

    /**
     * @param $product
     * @return string
     */
    public function formatAdditionalAttributes($product)
    {
        $product['alternate_code'] = $this->formatAlternateCodes($product['resources']['itemsAlternatecodes']);

        $attributes = '';
        $sanitizedAttributes = array_diff(self::P21_ATTRIBUTES, $this->getExcludedAttributes());
        foreach ($sanitizedAttributes as $attributeKey) {
            $attributes .= "p21_{$attributeKey}={$product[$attributeKey]},";
        }
        return rtrim($attributes, ',');
    }

    /**
     * @param $alternateCodes
     * @return string
     */
    public function formatAlternateCodes($alternateCodes)
    {
        $codes = implode(',', array_column($alternateCodes, 'alternate_code'));
        return $codes;
    }

    /**
     * Generate CSV file with product data
     *
     * @api
     * @return array
     */
    public function generateFile()
    {
        try {
            set_time_limit(0);
            $this->logger->info("Generate products data file");

            $directory = $this->getImportDir();
            $file = $directory . '/products.csv';
            $historicalFile = $directory . '/products-' . date('Ymd-Hi') . '.csv';

            $filePath = $this->io->cp($file, $historicalFile);

            $handle = fopen($file, 'w');

            $data = [];
            $productsData = $this->products();

            $productIndex = 0;
            foreach ($productsData['products'] as $product) {
                if ($productIndex == 0) {
                    fputcsv($handle, array_keys($product));
                }
                fputcsv($handle, $product);
                $productIndex++;
            }

            if (filesize($file)) {
                $this->logger->info("Product import file is successfully created");
            } else {
                $this->logger->info("Product import file is empty.");
            }
        } catch (\Throwable $e) {
            $this->logger->error($e);
        }
    }
}
