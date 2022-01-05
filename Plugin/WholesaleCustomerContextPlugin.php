<?php
namespace Ripen\Prophet21\Plugin;

use Ripen\Prophet21\Helper\WholesaleHelper;

/**
 * Plugin on \Magento\Framework\App\Http\Context
 */
class WholesaleCustomerContextPlugin
{
    public function __construct(
        \Magento\Customer\Model\Session $customerSession,
        WholesaleHelper $wholesaleHelper
    ) {
        $this->customerSession = $customerSession;
        $this->wholesaleHelper = $wholesaleHelper;
    }

    public function beforeGetVaryString(\Magento\Framework\App\Http\Context $subject)
    {
        $subject->setValue('CONTEXT_WHOLESALE', (int)$this->wholesaleHelper->isCustomerWholesale($this->customerSession->getCustomer()), 0);
    }
}

