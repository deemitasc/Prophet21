<?php

namespace Ripen\Prophet21\Model\Config;

use \Magento\Framework\App\Config\ScopeConfigInterface;
use \Magento\Payment\Model\Config;

class PaymentMethods extends \Magento\Framework\DataObject implements \Magento\Framework\Data\OptionSourceInterface
{
    /**
     * @var ScopeConfigInterface
     */
    protected $appConfigScopeConfigInterface;

    /**
     * @var Config
     */
    protected $paymentModelConfig;

    /**
     * @param ScopeConfigInterface $appConfigScopeConfigInterface
     * @param Config $paymentModelConfig
     */
    public function __construct(
        ScopeConfigInterface $appConfigScopeConfigInterface,
        Config $paymentModelConfig
    ) {
        $this->appConfigScopeConfigInterface = $appConfigScopeConfigInterface;
        $this->paymentModelConfig = $paymentModelConfig;
    }

    public function toOptionArray()
    {
        $payments = $this->paymentModelConfig->getActiveMethods();
        $methods = [];
        foreach ($payments as $paymentCode => $paymentModel) {
            $paymentTitle = $this->appConfigScopeConfigInterface->getValue('payment/' . $paymentCode . '/title');
            $methods[$paymentCode] = [
                'label' => $paymentTitle,
                'value' => $paymentCode
            ];
        }
        return $methods;
    }
}
