<?php

namespace Ripen\Prophet21\Logger;

use Magento\Store\Model\ScopeInterface;

class EmailHandler extends \Monolog\Handler\MailHandler
{
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Magento\Framework\Mail\Template\TransportBuilder
     */
    protected $transportBuilder;

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\Mail\Template\TransportBuilder $transportBuilder
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Mail\Template\TransportBuilder $transportBuilder
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->transportBuilder = $transportBuilder;

        $threshold = $scopeConfig->getValue('p21/email_alerts/threshold');
        parent::__construct(Logger::toMonologLevel($threshold));
    }

    /**
     * Send a mail with the given content
     *
     * @param string $content formatted email body to be sent
     * @param array  $records the array of log records that formed this content
     */
    protected function send($content, array $records)
    {
        $recipients = $this->scopeConfig->getValue('p21/email_alerts/recipients');
        $emails = explode(',', $recipients);

        if (empty($emails)) {
            return;
        }

        $sender = [
            'name' => $this->scopeConfig->getValue('trans_email/ident_support/name', ScopeInterface::SCOPE_STORE),
            'email' => $this->scopeConfig->getValue('trans_email/ident_support/email', ScopeInterface::SCOPE_STORE),
        ];

        $data = [
            'level' => Logger::getLevelName($this->getHighestRecord($records)['level']),
            'content' => $content,
            'logs' => $records,
        ];

        try {
            $transport = $this->transportBuilder
                ->setTemplateIdentifier('p21_notification_template')
                ->setTemplateOptions(
                    [
                        'area' => \Magento\Framework\App\Area::AREA_ADMINHTML,
                        'store' => \Magento\Store\Model\Store::DEFAULT_STORE_ID,
                    ]
                )
                ->setTemplateVars($data)
                ->setFrom($sender)
                ->addTo($emails)
                ->getTransport();

            $transport->sendMessage();
        } catch (\Throwable $e) {
            // do nothing on purpose
        }
    }
}
