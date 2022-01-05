<?php

namespace Ripen\Prophet21\Controller\SalesRep;

use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Customer extends \Magento\Framework\App\Action\Action
{
    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @var \Ripen\Prophet21\Helper\Customer
     */
    protected $customerHelper;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param \Ripen\Prophet21\Helper\Customer $customerHelper
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        \Ripen\Prophet21\Helper\Customer $customerHelper
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->customerHelper = $customerHelper;
    }

    public function execute()
    {
        // just in case this page was stumbled upon by a non-sales rep
        $isLoggedInCustomerASalesRep = false;
        try {
            $isLoggedInCustomerASalesRep = $this->customerHelper->isLoggedInCustomerASalesRep();
        }
        catch (\Exception $e) {
            // gracefully fail
        }
        if (! $isLoggedInCustomerASalesRep) {
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath($this->_redirect->getRefererUrl());
            return $resultRedirect;
        }

        /** @var \Magento\Framework\View\Result\Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->set(__('Sales Rep Assigned Customers'));

        $block = $resultPage->getLayout()->getBlock('customer.account.link.back');
        if ($block) {
            $block->setRefererUrl($this->_redirect->getRefererUrl());
        }
        return $resultPage;
    }
}
