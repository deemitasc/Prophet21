<?php
/**
 * Overrides \Magento\Catalog\Pricing\Render
 */

namespace Ripen\Prophet21\Block\Pricing;

use Magento\Framework\Pricing\Render as PricingRender;
use Magento\Framework\Pricing\SaleableInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Ripen\Prophet21\Helper\MultistoreHelper;

class Render extends \Magento\Catalog\Pricing\Render
{
    /**
     * @var MultistoreHelper
     */
    protected $multistoreHelper;

    /**
     * @var array
     */
    protected $priceTypesToOverride = [
        'wishlist_configured_price',
    ];

    /**
     * @param Template\Context $context
     * @param Registry $registry
     * @param MultistoreHelper $multistoreHelper
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        Registry $registry,
        MultistoreHelper $multistoreHelper,
        array $data = []
    ) {
        parent::__construct($context, $registry, $data);

        $this->multistoreHelper = $multistoreHelper;
    }

    /**
     * Overrides parent function for certain price type codes
     *
     * {@inheritdoc}
     */
    protected function _toHtml()
    {
        $priceTypeCode = $this->getPriceTypeCode();

        if (! $this->multistoreHelper->isWholesaleWebsite() || ! in_array($priceTypeCode, $this->priceTypesToOverride)) {
            return parent::_toHtml();
        }

        /** @var PricingRender $priceRender */
        $priceRender = $this->getLayout()->getBlock($this->getPriceRender());
        if ($priceRender instanceof PricingRender) {
            $product = $this->getProduct();
            if ($product instanceof SaleableInterface) {
                $arguments = $this->getData();
                $arguments['render_block'] = $this;
                $arguments['css_classes'] = (! empty($arguments['css_classes']) ? $arguments['css_classes'] . ' ' . 'inactive' : 'inactive');
                return $priceRender->render($priceTypeCode, $product, $arguments);
            }
        }
        return parent::_toHtml();
    }
}
