<?php
/**
 * Overrides Magento\InventorySales\Plugin\StockState\CheckQuoteItemQtyPlugin
 */

namespace Ripen\Prophet21\Plugin;

use Magento\CatalogInventory\Api\StockStateInterface;
use Magento\Framework\DataObject;
use Magento\Framework\DataObject\Factory as ObjectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Locale\FormatInterface;
use Magento\InventoryCatalogApi\Model\GetSkusByProductIdsInterface;
use Magento\InventorySales\Model\IsProductSalableCondition\BackOrderNotifyCustomerCondition;
use Magento\InventorySales\Model\IsProductSalableForRequestedQtyCondition\ProductSalabilityError;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\IsProductSalableForRequestedQtyInterface;
use Magento\InventorySalesApi\Api\StockResolverInterface;
use Magento\Store\Model\StoreManagerInterface;
use Ripen\SimpleApps\Model\Api;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use Magento\Checkout\Model\Session as CheckoutSession;
use Ripen\Prophet21\Logger\Logger;

class CheckQuoteItemQtyPlugin
{
    /**
     * @var ObjectFactory
     */
    protected $objectFactory;

    /**
     * @var FormatInterface
     */
    protected $format;

    /**
     * @var IsProductSalableForRequestedQtyInterface
     */
    protected $isProductSalableForRequestedQty;

    /**
     * @var GetSkusByProductIdsInterface
     */
    protected $getSkusByProductIds;

    /**
     * @var StockResolverInterface
     */
    protected $stockResolver;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var BackOrderNotifyCustomerCondition
     */
    protected $backOrderNotifyCustomerCondition;

    /**
     * @var Api
     */
    protected $api;

    /**
     * @var MessageManager
     */
    protected $messageManager;

    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @param ObjectFactory $objectFactory
     * @param FormatInterface $format
     * @param IsProductSalableForRequestedQtyInterface $isProductSalableForRequestedQty
     * @param GetSkusByProductIdsInterface $getSkusByProductIds
     * @param StockResolverInterface $stockResolver
     * @param StoreManagerInterface $storeManager
     * @param BackOrderNotifyCustomerCondition $backOrderNotifyCustomerCondition
     * @param Api $api
     * @param MessageManager $messageManager
     * @param CheckoutSession $checkoutSession
     * @param Logger $logger
     * @SuppressWarnings(PHPMD.LongVariable)
     */
    public function __construct(
        ObjectFactory $objectFactory,
        FormatInterface $format,
        IsProductSalableForRequestedQtyInterface $isProductSalableForRequestedQty,
        GetSkusByProductIdsInterface $getSkusByProductIds,
        StockResolverInterface $stockResolver,
        StoreManagerInterface $storeManager,
        BackOrderNotifyCustomerCondition $backOrderNotifyCustomerCondition,
        Api $api,
        MessageManager $messageManager,
        CheckoutSession $checkoutSession,
        Logger $logger
    ) {
        $this->objectFactory = $objectFactory;
        $this->format = $format;
        $this->isProductSalableForRequestedQty = $isProductSalableForRequestedQty;
        $this->getSkusByProductIds = $getSkusByProductIds;
        $this->stockResolver = $stockResolver;
        $this->storeManager = $storeManager;
        $this->backOrderNotifyCustomerCondition = $backOrderNotifyCustomerCondition;
        $this->api = $api;
        $this->messageManager = $messageManager;
        $this->checkoutSession = $checkoutSession;
        $this->logger = $logger;
    }

    /**
     * Most of the code in this function is the same as
     * Magento\InventorySales\Plugin\StockState\CheckQuoteItemQtyPlugin::aroundCheckQuoteItemQty,
     * with the only addition being the netStock check against the API at the end
     *
     * @param StockStateInterface $subject - not used due to deprecation
     * @param \Closure $proceed
     * @param int $productId
     * @param float $itemQty
     * @param float $qtyToCheck
     * @param float $origQty
     * @param int|null $scopeId
     *
     * @return DataObject
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws \Ripen\Prophet21\Exception\P21ApiException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundCheckQuoteItemQty(
        StockStateInterface $subject,
        \Closure $proceed,
        $productId,
        $itemQty,
        $qtyToCheck,
        $origQty,
        $scopeId = null
    ) {
        $result = $this->objectFactory->create();
        $result->setHasError(false);

        $qty = $this->getNumber($itemQty);

        $skus = $this->getSkusByProductIds->execute([$productId]);
        $productSku = $skus[$productId];

        $websiteCode = $this->storeManager->getWebsite()->getCode();
        $stock = $this->stockResolver->execute(SalesChannelInterface::TYPE_WEBSITE, $websiteCode);
        $stockId = $stock->getStockId();

        $isSalableResult = $this->isProductSalableForRequestedQty->execute($productSku, (int)$stockId, $qty);

        if ($isSalableResult->isSalable() === false) {
            /** @var ProductSalabilityError $error */
            foreach ($isSalableResult->getErrors() as $error) {
                $result->setHasError(true)->setMessage($error->getMessage())->setQuoteMessage($error->getMessage())
                    ->setQuoteMessageIndex('qty');
            }
        }

        $productSalableResult = $this->backOrderNotifyCustomerCondition->execute($productSku, (int)$stockId, $qty);
        if ($productSalableResult->getErrors()) {
            /** @var ProductSalabilityError $error */
            foreach ($productSalableResult->getErrors() as $error) {
                $result->setMessage($error->getMessage());
            }
        }

        try {
            /**
             * Check net stock on API and add message as needed.  Note that we do not setHasError to true as doing so
             * will prevent the cart from being able to be checked out
             */
            $netStock = $this->api->getItemNetStock($productSku);

            if ($qty > $netStock) {
                if ($netStock >= 1) {
                    $itemMessage = __(
                        'Only %1 in stock, so remaining %2 will be backordered.',
                        $netStock,
                        ($qty - $netStock)
                    );
                } else {
                    $itemMessage = __('None in stock, so will be backordered.');
                }
                $quoteMessage = 'Some of the products are backordered.';

                // set the error on the quote item
                $result->setMessage($itemMessage)->setQuoteMessage($quoteMessage)->setQuoteMessageIndex('qty');

                /**
                 * Since we aren't setting setHasError to true, manually trigger the error message modal.
                 * Construct and set message in a way to avoid having the error message modal appear multiple times
                 */
                if (!$this->checkoutSession->getQuoteError()) {
                    $message = $this->messageManager->createMessage(\Magento\Framework\Message\MessageInterface::TYPE_ERROR, 'quote_item_qty_message_identifier')->setText($quoteMessage);
                    $messages = [];
                    $messages[] = $message;
                    $this->messageManager->addUniqueMessages($messages);
                    $this->checkoutSession->setQuoteError(true);    // set custom checkoutSession variable so that this quote error doesn't appear multiple times
                }
            }
        } catch(\Exception $e) {
            // unable to fetch stock data with api call, or api has not yet been set up
            $this->logger->error($e->getMessage());
        }

        return $result;
    }

    /**
     * Convert quantity to a valid float
     *
     * @param string|float|int|null $qty
     *
     * @return float|null
     */
    protected function getNumber($qty)
    {
        if (!is_numeric($qty)) {
            return $this->format->getNumber($qty);
        }

        return $qty;
    }
}
