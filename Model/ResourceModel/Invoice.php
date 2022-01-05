<?php

namespace Ripen\Prophet21\Model\ResourceModel;

class Invoice extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    /**
     * Explicitly setting this to false in order to enable saving of records with customized primary column values
     * @var bool
     */
    protected $_isPkAutoIncrement = false;

    protected function _construct()
    {
        $this->_init('p21_invoice', 'id');
    }
}
