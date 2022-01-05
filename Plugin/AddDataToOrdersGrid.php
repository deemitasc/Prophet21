<?php

namespace Ripen\Prophet21\Plugin;

/**
 * Class AddDataToOrdersGrid
 */
class AddDataToOrdersGrid
{
    /**
     * @param \Magento\Framework\View\Element\UiComponent\DataProvider\CollectionFactory $subject
     * @param \Magento\Sales\Model\ResourceModel\Order\Grid\Collection $collection
     * @param $requestName
     * @return mixed
     */
    public function afterGetReport($subject, $collection, $requestName)
    {

        if ($requestName !== 'sales_order_grid_data_source') {
            return $collection;
        }

        if ($collection->getMainTable() === $collection->getResource()->getTable('sales_order_grid')) {
            $joinTable = $collection->getTable('sales_order');
            $collection->getSelect()->joinLeft(
                ['sales_order_table' => $joinTable],
                'main_table.entity_id = sales_order_table.entity_id',
                ['p21_order_no'] // Add additional order attributes if required
            );
        }

        return $collection;
    }
}