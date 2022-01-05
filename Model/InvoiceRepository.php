<?php

namespace Ripen\Prophet21\Model;

class InvoiceRepository
{
    const PROCESSED_STATUS = 1;
    const NOT_PROCESSED_STATUS = 0;

    /**
     * @var InvoiceFactory
     */
    protected $invoiceFactory;

    /**
     * @var ResourceModel\Invoice
     */
    protected $resourceModel;

    /**
     * @var ResourceModel\Invoice\CollectionFactory
     */
    protected $collectionFactory;

    /**
     * InvoiceRepository constructor.
     * @param InvoiceFactory $invoiceFactory
     * @param ResourceModel\Invoice $resourceModel
     * @param ResourceModel\Invoice\CollectionFactory $collectionFactory
     */
    public function __construct(
        \Ripen\Prophet21\Model\InvoiceFactory $invoiceFactory,
        \Ripen\Prophet21\Model\ResourceModel\Invoice $resourceModel,
        \Ripen\Prophet21\Model\ResourceModel\Invoice\CollectionFactory $collectionFactory
    ){
        $this->resourceModel = $resourceModel;
        $this->invoiceFactory = $invoiceFactory;
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * @param Invoice $invoice
     * @return mixed|Invoice
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function save(\Ripen\Prophet21\Model\Invoice $invoice)
    {
        try {
            $this->resourceModel->save($invoice);
        } catch (\Exception $exception) {
            throw new \Magento\Framework\Exception\CouldNotSaveException(__($exception->getMessage()));
        }
        return $invoice;
    }

    /**
     * @param $invoiceId
     * @return mixed
     */
    public function get($invoiceId)
    {
        $invoice = $this->invoiceFactory->create();
        $invoice->load($invoiceId);
        if (!$invoice->getId()) {
            $invoice->setId($invoiceId);
        }
        return $invoice;
    }

    /**
     * @param $invoiceId
     * @return bool|mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function isInvoiceProcessed($invoiceId)
    {
        $collection = $this->collectionFactory->create();

        /** @var \Magento\Framework\DataObject $records */
        $count = $collection->addFieldToFilter('processed', self::PROCESSED_STATUS)
            ->addFieldToFilter($this->resourceModel->getIdFieldName(), $invoiceId)
            ->getSize();

        return $count > 0;
    }
}
