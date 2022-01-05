<?php

namespace Ripen\Prophet21\Plugin;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\ResourceModel\Order as OrderResource;

class OrderSenderDelay
{
    /**
     * @var ScopeConfigInterface
     */
    protected $globalConfig;

    /**
     * @var TimezoneInterface
     */
    protected $dateTime;

    /**
     * @var OrderResource
     */
    protected $orderResource;

    /**
     * @param ScopeConfigInterface $globalConfig
     * @param TimezoneInterface $dateTime
     * @param OrderResource $orderResource
     */
    public function __construct(
        ScopeConfigInterface $globalConfig,
        TimezoneInterface $dateTime,
        OrderResource $orderResource
    ) {
        $this->globalConfig = $globalConfig;
        $this->dateTime = $dateTime;
        $this->orderResource = $orderResource;
    }

    /**
     * When using P21 order numbers, delay order confirmation email for the P21 order number to be received,
     * up to a configured threshold, after which it is sent out with the temporary Magento order number if we
     * haven't yet received the P21 order number.
     *
     * @param OrderSender $subject
     * @param callable $proceed
     * @param Order $order
     * @param bool $forceSyncMode
     * @return bool
     */
    public function aroundSend(OrderSender $subject, callable $proceed, Order $order, $forceSyncMode = false)
    {
        $useP21OrderNumbers = (bool) $this->globalConfig->getValue('p21/integration/use_p21_order_numbers');
        $emailSyncTimeout = (int) $this->globalConfig->getValue('p21/integration/email_sync_timeout');

        // NOTE: $forceSyncMode is only true when running the async send cron job. This differentiates
        // from the call to this same method that is made when the order is first placed.
        if ($forceSyncMode && $useP21OrderNumbers && $emailSyncTimeout) {
            $orderTime = $this->dateTime->date($order->getData('created_at'));
            $thresholdTime = $this->dateTime->date(strtotime("-{$emailSyncTimeout} minute"));
            if (empty($order->getData('p21_order_no')) && $orderTime > $thresholdTime) {
                return false;
            }
        }
        return $proceed($order, $forceSyncMode);
    }
}
