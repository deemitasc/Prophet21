<?php

namespace Ripen\Prophet21\Model;

class Invoice extends \Magento\Framework\Model\AbstractModel
{

    /**
     * @var InvoiceRepository
     */
    protected $invoiceRepository;

    /**
     * Invoice constructor.
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     * @param InvoiceRepository $invoiceRepository
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = [],
        \Ripen\Prophet21\Model\InvoiceRepository $invoiceRepository
    ) {
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);

        $this->_init('Ripen\Prophet21\Model\ResourceModel\Invoice');

        $this->invoiceRepository = $invoiceRepository;
    }

    /**
     * @param $processedStatus
     * @return $this
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function setStatus($processedStatus)
    {
        $this->setData('processed', (int)$processedStatus);
        $this->invoiceRepository->save($this);

        return $this;
    }

    /**
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function markAsProcessed()
    {
        $this->setStatus(\Ripen\Prophet21\Model\InvoiceRepository::PROCESSED_STATUS);
    }
}
