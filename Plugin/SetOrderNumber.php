<?php

namespace Ripen\Prophet21\Plugin;

use Magento\Framework\App\Config\ScopeConfigInterface;

class SetOrderNumber
{
    const TEMP_ORDER_SUFFIX = '-PENDING';

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * SetOrderNumber constructor.
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    public function afterGetCurrentValue(
        \Magento\SalesSequence\Model\Sequence $subject,
        string $result
    ) {
        if ($this->scopeConfig->getValue('p21/integration/use_p21_order_numbers')) {
            $result .= self::TEMP_ORDER_SUFFIX;
        }

        return $result;
    }
}
