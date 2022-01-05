<?php

namespace Ripen\Prophet21\Model\ResourceModel\PickList;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    protected function _construct()
    {
        $this->_init(
            'Ripen\Prophet21\Model\PickList',
            'Ripen\Prophet21\Model\ResourceModel\PickList'
        );
    }
}
