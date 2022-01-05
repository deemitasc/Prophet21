<?php
/**
 * Plugin to check for customer-based pricing on order creation
 */

namespace Ripen\Prophet21\Plugin;

use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote\Item;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Customer\Model\CustomerFactory;
use Psr\Log\LoggerInterface;
use Ripen\Prophet21\Model\CustomerPrices;
use Ripen\Prophet21\Helper\WholesaleHelper;

class SetCustomItemPricesOnOrderCreation
{
    /**
     * @var Session
     */
    protected $checkoutSession;

    /**
     * @var CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @var CustomerPrices
     */
    protected $customerPrices;

    /**
     * @var WholesaleHelper
     */
    protected $wholesaleHelper;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var CustomerFactory
     */
    protected $customerFactory;

    /**
     * SetCustomItemPricesOnOrderCreation constructor.
     * @param Session $checkoutSession
     * @param CartRepositoryInterface $quoteRepository
     * @param CustomerPrices $customerPrices
     * @param WholesaleHelper $wholesaleHelper
     * @param LoggerInterface $logger
     * @param CustomerFactory $customerFactory
     */
    public function __construct(
        Session $checkoutSession,
        CartRepositoryInterface $quoteRepository,
        CustomerPrices $customerPrices,
        WholesaleHelper $wholesaleHelper,
        LoggerInterface $logger,
        CustomerFactory $customerFactory
    )
    {
        $this->checkoutSession = $checkoutSession;
        $this->quoteRepository = $quoteRepository;
        $this->customerPrices = $customerPrices;
        $this->wholesaleHelper = $wholesaleHelper;
        $this->logger = $logger;
        $this->customerFactory = $customerFactory;
    }

    /**
     * Set P21-based custom item prices for orders created directly (not via normal cart/checkout).
     * Typically these are API-created orders, such as for punchout.
     *
     * @param CartManagementInterface $subject
     * @param $cartId
     */
    public function beforePlaceOrder(
        CartManagementInterface $subject,
        $cartId
    ) {
        try {
            $quote = $this->quoteRepository->getActive($cartId);
            $customer = $this->customerFactory->create()->load($quote->getCustomer()->getId());

            if ($this->wholesaleHelper->isCustomerWholesale($customer)) {
                $checkoutQuoteId = $this->checkoutSession->getQuoteId();

                // Make sure we aren't running this for orders coming from checkout, as those orders are
                // taken care of by \Ripen\Prophet21\Observer\Sales\SetCustomItemPricesOnCartChange
                if (empty($checkoutQuoteId) || $checkoutQuoteId !== $quote->getId()) {
                    $this->customerPrices->applyP21PricingToQuote($quote);

                    // Must re-set the items here to have them saved correctly, but must not do this inside
                    // applyP21PricingToQuote or it may cause a double add to cart on the frontend due
                    // to internal Magento inconsistencies between the $_items collection and the
                    // $_data['items'] array on the quote object.
                    $quote->setItems($quote->getAllItems());

                    $quote->collectTotals();
                    $this->quoteRepository->save($quote);
                }
            }
        } catch (NoSuchEntityException $e) {
            $this->logger->error($e->getMessage());
        }
    }
}
