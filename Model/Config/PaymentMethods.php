<?php

namespace Ripen\Prophet21\Model\Config;

use \Magento\Framework\App\Config\ScopeConfigInterface;
use \Magento\Payment\Api\PaymentMethodListInterface;
use \Magento\Framework\App\RequestInterface;

class PaymentMethods extends \Magento\Framework\DataObject implements \Magento\Framework\Data\OptionSourceInterface
{
    /**
     * @var ScopeConfigInterface
     */
    protected $appConfigScopeConfigInterface;

    /**
     * @var PaymentMethodListInterface
     */
    protected $paymentMethodList;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @param ScopeConfigInterface $appConfigScopeConfigInterface
     * @param PaymentMethodListInterface $paymentMethodList
     * @param RequestInterface $request
     */
    public function __construct(
        ScopeConfigInterface $appConfigScopeConfigInterface,
        PaymentMethodListInterface $paymentMethodList, 
        RequestInterface $request
    ) {
        $this->appConfigScopeConfigInterface = $appConfigScopeConfigInterface;
        $this->paymentMethodList = $paymentMethodList;
        $this->request = $request;
    }

    public function toOptionArray()
    {
        $storeId = (int)$this->request->getParam('store');
        $payments = $this->paymentMethodList->getActiveList($storeId);
        $methods = [];
        foreach ($payments as $payment) {
            $methods[$payment->getCode()] = [
                'label' => $payment->getTitle(),
                'value' => $payment->getCode()
            ];
        }
        return $methods;
    }
}
