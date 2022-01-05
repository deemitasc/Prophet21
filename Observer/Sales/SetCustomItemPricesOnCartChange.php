<?php

namespace Ripen\Prophet21\Observer\Sales;


class SetCustomItemPricesOnCartChange implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @var \Ripen\Prophet21\Logger\Logger
     */
    protected $logger;

    /**
     * @var \Ripen\Prophet21\Model\CustomerUOM
     */
    protected $customerUOM;

    /**
     * @var \Ripen\Prophet21\Model\CustomerPrices
     */
    protected $customerPrices;

    /**
     * @var \Ripen\Prophet21\Helper\MultistoreHelper
     */
    protected $multistoreHelper;

    /**
     * @var \Ripen\Prophet21\Helper\WholesaleHelper
     */
    protected $wholesaleHelper;

    /**
     * @var \Ripen\Prophet21\Helper\Product
     */
    protected $p21ProductHelper;

    /**
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Ripen\Prophet21\Logger\Logger $logger
     * @param \Ripen\Prophet21\Model\CustomerPrices $customerPrices
     * @param \Ripen\Prophet21\Helper\WholesaleHelper $wholesaleHelper
     * @param \Ripen\Prophet21\Helper\Product $p21ProductHelper
     */
    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession,
        \Ripen\Prophet21\Logger\Logger $logger,
        \Ripen\Prophet21\Model\Products $p21Products,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Ripen\Prophet21\Model\CustomerUOM $customerUOM,
        \Ripen\Prophet21\Model\CustomerPrices $customerPrices,
        \Ripen\Prophet21\Helper\WholesaleHelper $wholesaleHelper,
        \Ripen\Prophet21\Helper\Product $p21ProductHelper
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->logger = $logger;
        $this->customerUOM = $customerUOM;
        $this->customerPrices = $customerPrices;
        $this->wholesaleHelper = $wholesaleHelper;
        $this->messageManager = $messageManager;
        $this->p21ProductHelper = $p21ProductHelper;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if (! $this->wholesaleHelper->isLoggedInCustomerWholesale() && ! $this->p21ProductHelper->isUOMSelectionEnabled()) {
            return;
        }

        try {
            /** @var \Magento\Checkout\Model\Cart $cart */
            $cart = $observer->getEvent()->getData('cart');
            $quote = $cart->getQuote();

            if ($this->p21ProductHelper->isUOMSelectionEnabled()) {
                $this->customerUOM->applyCustomerUOMToQuote($quote);
            }

            if ($this->wholesaleHelper->isLoggedInCustomerWholesale()) {
                $this->customerPrices->applyP21PricingToQuote($quote);
            }
        } catch (\Exception $e) {
            // unable to fetch custom prices; log error and inform customer
            $this->messageManager->addErrorMessage(__('We temporarily cannot retrieve pricing for your order, so totals shown may be inaccurate. Please try again later.'));
        }
    }
}
