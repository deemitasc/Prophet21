<?php

namespace Ripen\Prophet21\Block\Order;

use Magento\Framework\View\Element\Template;
use Magento\Framework\App\Config\ScopeConfigInterface;

class SuccessMessage extends Template
{
    /**
     * @var string
     */
    protected $_template = 'Ripen_Prophet21::order/success_message.phtml';

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * SuccessMessage constructor.
     * @param Template\Context $context
     * @param ScopeConfigInterface $scopeConfig
     * @param array $data
     */
    public function __construct(Template\Context $context, ScopeConfigInterface $scopeConfig, array $data = [])
    {
        parent::__construct($context, $data);
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @return int
     */
    public function getEmailDelayTimer()
    {
        return (int)$this->scopeConfig->getValue('p21/integration/email_delay_timer');
    }
}
