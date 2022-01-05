<?php

namespace Ripen\Prophet21\Model;

class PickListRepository
{
    const PROCESSED_STATUS = 1;
    const NOT_PROCESSED_STATUS = 0;

    /**
     * @var \Ripen\Prophet21\Model\PickListFactory
     */
    protected $pickListFactory;

    /**
     * @var \Ripen\Prophet21\Model\ResourceModel\PickList
     */
    protected $resourceModel;

    /**
     * @var \Ripen\Prophet21\Model\ResourceModel\PickList\CollectionFactory
     */
    protected $collectionFactory;

    public function __construct(
        \Ripen\Prophet21\Model\PickListFactory $pickListFactory,
        \Ripen\Prophet21\Model\ResourceModel\PickList $resourceModel,
        \Ripen\Prophet21\Model\ResourceModel\PickList\CollectionFactory $collectionFactory
    ){
        $this->resourceModel = $resourceModel;
        $this->pickListFactory = $pickListFactory;
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * @param PickList $pickList
     * @return mixed|PickList
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function save(\Ripen\Prophet21\Model\PickList $pickList)
    {
        try {
            $this->resourceModel->save($pickList);
        } catch (\Exception $exception) {
            throw new \Magento\Framework\Exception\CouldNotSaveException(__($exception->getMessage()));
        }
        return $pickList;
    }

    /**
     * @param $pickListId
     * @return \Ripen\Prophet21\Model\PickList
     */
    public function get($pickListId)
    {
        $pickList = $this->pickListFactory->create();
        $pickList->load($pickListId);
        if (!$pickList->getId()) {
            $pickList->setId($pickListId);
        }
        return $pickList;
    }


    /**
     * @param $pickListId
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function isPickListProcessed($pickListId)
    {
        $collection = $this->collectionFactory->create();

        /** @var \Magento\Framework\DataObject $records */
        $count = $collection->addFieldToFilter('processed', self::PROCESSED_STATUS)
            ->addFieldToFilter($this->resourceModel->getIdFieldName(), $pickListId)
            ->getSize();

        return $count > 0;
    }
}
