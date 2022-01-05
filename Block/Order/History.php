<?php
/**
 * Overrides \Magento\Sales\Block\Order\History
 */

namespace Ripen\Prophet21\Block\Order;

class History extends \Magento\Sales\Block\Order\History
{
    /**
     * @var string
     */
    protected $_template = 'Ripen_Prophet21::order/history.phtml';

    /**
     * @var \Magento\Framework\App\Request\Http
     */
    protected $request;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\TimezoneInterface
     */
    protected $timezone;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Sales\Model\Order\Config $orderConfig,
        \Magento\Framework\App\Request\Http $request,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $timezone,
        array $data = [])
    {
        parent::__construct($context, $orderCollectionFactory, $customerSession, $orderConfig, $data);

        $this->request = $request;
        $this->timezone = $timezone;
    }

    /**
     * @return bool|\Magento\Sales\Model\ResourceModel\Order\Collection
     */
    public function getOrderHistory()
    {
        $customerId = $this->_customerSession->getCustomerId();
        if (! $customerId) {
            return false;
        }
        if (!$this->orders) {
            $this->orders = $this->_orderCollectionFactory->create($customerId)->addFieldToSelect(
                '*'
            )->addFieldToFilter(
                'status',
                ['in' => $this->_orderConfig->getVisibleOnFrontStatuses()]
            )->setOrder(
                'created_at',
                'desc'
            );

            $this->orders->getSelect()
                ->join(
                    ['item' => 'sales_order_item'],
                    'item.order_id = main_table.entity_id',
                    []
                )->group('main_table.entity_id');

            $keyword = $this->request->getParam('keyword');
            $qty = $this->request->getParam('qty');
            $fromDate = $this->request->getParam('from_date');
            $toDate = $this->request->getParam('to_date');

            if (! empty($keyword)) {
                $keyword = strtr($keyword, ['%' => '\%', '_' => '\_']);
                $this->orders->addFieldToFilter(
                    [
                        'item.sku',
                        'item.name',
                        'item.description',
                    ],
                    [
                       ['like' => '%'.$keyword.'%'],
                       ['like' => '%'.$keyword.'%'],
                       ['like' => '%'.$keyword.'%'],
                    ]
                );
            }
            if (! empty($qty)) {
                $this->orders->addAttributeToFilter('item.qty_ordered', $qty);
            }
            $createdAtCondition = [];

            if (! empty($fromDate)) {
                $createdAtCondition['from'] = $this->timezone->convertConfigTimeToUtc($fromDate);
            }
            if (! empty($toDate)) {
                $createdAtCondition['to'] = $this->timezone->convertConfigTimeToUtc($toDate);
            }
            if (! empty($createdAtCondition)) {
                $this->orders->addFieldToFilter('main_table.created_at', $createdAtCondition);
            }
        }
        return $this->orders;
    }

    public function getRequestParam($key)
    {
        return $this->request->getParam($key);
    }
}
