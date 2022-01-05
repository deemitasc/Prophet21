<?php

namespace Ripen\Prophet21\Block\CatalogInventory;

class Availability extends \Magento\Framework\View\Element\Template
{
    /**
     * @var \Magento\Catalog\Api\ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * Availability constructor.
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        array $data = []
    ) {
        $this->_template = 'stockqty/availability.phtml';
        $this->productRepository = $productRepository;
        parent::__construct($context, $data);
    }

    /**
     * @param $sku
     * @return \Magento\Catalog\Api\Data\ProductInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getProduct($sku)
    {
        return $this->productRepository->get($sku);
    }
}
