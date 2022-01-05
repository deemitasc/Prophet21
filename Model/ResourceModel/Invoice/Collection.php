<?php

namespace Ripen\Prophet21\Model\ResourceModel\Invoice;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    protected function _construct()
    {
        $this->_init(
            'Ripen\Prophet21\Model\Invoice',
            'Ripen\Prophet21\Model\ResourceModel\Invoice'
        );
    }
}
