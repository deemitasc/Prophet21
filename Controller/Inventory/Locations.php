<?php

namespace Ripen\Prophet21\Controller\Inventory;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\NoSuchEntityException;

class Locations extends \Magento\Framework\App\Action\Action
{
    /**
     * @var ResultFactory
     */
    protected $resultFactory;

    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @param Context $context
     * @param ResultFactory $resultFactory
     * @param ProductRepositoryInterface $productRepository
     */
    public function __construct(
        Context $context,
        ResultFactory $resultFactory,
        ProductRepositoryInterface $productRepository
    ) {
        parent::__construct($context);
        $this->resultFactory = $resultFactory;
        $this->productRepository = $productRepository;
    }

    public function execute()
    {
        if (!$this->getRequest()->isAjax()) {
            return $this->_redirect('/');
        }
        $resultLayout = $this->resultFactory->create(ResultFactory::TYPE_LAYOUT);
        $block = $resultLayout->getLayout()->getBlock('prophet21_inventory_locations');
        // phpcs:ignore Magento2.Functions.DiscouragedFunction
        $sku = base64_decode($this->getRequest()->getParam('sku'));
        // phpcs:ignore Magento2.Security.LanguageConstruct.ExitUsage
        $product = $this->productRepository->get($sku);
        $block->setData('product', $product);
        return $resultLayout;
    }
}
