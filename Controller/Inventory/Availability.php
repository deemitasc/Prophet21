<?php

namespace Ripen\Prophet21\Controller\Inventory;

use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\View\Result\PageFactory;

class Availability extends \Magento\Framework\App\Action\Action
{
    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var \Ripen\Prophet21\Helper\MsiHelper
     */
    protected $msiHelper;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        JsonFactory $resultJsonFactory,
        \Ripen\Prophet21\Helper\MsiHelper $msiHelper
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->msiHelper = $msiHelper;

        parent::__construct($context);
    }

    /**
     * Renders Availability block per given sku
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $skus = $this->getRequest()->getParam('skus');
        $skus = json_decode(base64_decode($skus)) ?: [];
        $isCategoryPage = (bool) $this->getRequest()->getParam('isCategoryPage');

        $availabilityData = [];
        foreach ($skus as $sku) {
            $resultPage = $this->resultPageFactory->create();
            $block = $resultPage->getLayout()
                ->createBlock('Ripen\Prophet21\Block\CatalogInventory\Availability')
                ->setData('sku', $sku)
                ->setData('isCategoryPage', $isCategoryPage)
                ->toHtml();

            $availabilityData[$sku] = $block;
        }

        return $this->resultJsonFactory->create()->setData($availabilityData);
    }
}
