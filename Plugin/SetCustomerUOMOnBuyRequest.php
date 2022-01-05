<?php
/**
 * Plugin to make sure UOM value in the buyRequest is transferred for use in product parameter updates
 */

namespace Ripen\Prophet21\Plugin;


class SetCustomerUOMOnBuyRequest
{
    /**
     * @var \Ripen\Prophet21\Helper\Product
     */
    protected $p21ProductHelper;

    /**
     * SetCustomerUOMOnBuyRequest constructor.
     * @param \Ripen\Prophet21\Helper\Product $p21ProductHelper
     */
    public function __construct(
        \Ripen\Prophet21\Helper\Product $p21ProductHelper
    ) {
        $this->p21ProductHelper = $p21ProductHelper;
    }

    /**
     * @param \Magento\Catalog\Model\Product $product
     * @param \Magento\Framework\DataObject $results
     * @param \Magento\Framework\DataObject $buyRequest
     * @return \Magento\Framework\DataObject
     */
    public function afterProcessBuyRequest(
        \Magento\Catalog\Model\Product $product,
        \Magento\Framework\DataObject $results,
        \Magento\Framework\DataObject $buyRequest
    ) {
        if ($this->p21ProductHelper->isUOMSelectionEnabled()) {
            if (isset($buyRequest[\Ripen\Prophet21\Model\CustomerUOM::UOM_FORM_FIELD_NAME])) {
                $results->addData([
                    \Ripen\Prophet21\Model\CustomerUOM::UOM_FORM_FIELD_NAME => $buyRequest[\Ripen\Prophet21\Model\CustomerUOM::UOM_FORM_FIELD_NAME]
                ]);
            }
        }

        return $results;
    }
}
