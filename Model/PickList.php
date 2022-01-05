<?php
/**
 * Model for tracking Prophet21 Pick List numbers
 */

namespace Ripen\Prophet21\Model;

class PickList extends \Magento\Framework\Model\AbstractModel
{
    /**
     * @var PickListRepository
     */
    protected $pickListRepository;

    /**
     * PickList constructor.
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     * @param PickListRepository $pickListRepository
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = [],
        \Ripen\Prophet21\Model\PickListRepository $pickListRepository
    ) {
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);

        $this->_init('Ripen\Prophet21\Model\ResourceModel\PickList');

        $this->pickListRepository = $pickListRepository;
    }

    /**
     * @param int $processedStatus
     * @return $this|mixed
     */
    public function setStatus($processedStatus)
    {
        $this->setData('processed', (int)$processedStatus);
        $this->pickListRepository->save($this);

        return $this;
    }

    public function markAsProcessed()
    {
        $this->setStatus(\Ripen\Prophet21\Model\PickListRepository::PROCESSED_STATUS);
    }
}
