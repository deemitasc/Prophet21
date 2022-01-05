<?php
/**
 * Overrides \Magento\Catalog\Pricing\Render\FinalPriceBox
 */

namespace Ripen\Prophet21\Block\Pricing;

use Magento\Catalog\Model\Product\Pricing\Renderer\SalableResolverInterface;
use Magento\Catalog\Pricing\Price\MinimalPriceCalculatorInterface;
use Magento\Framework\Pricing\Price\PriceInterface;
use Magento\Framework\Pricing\Render\RendererPool;
use Magento\Framework\Pricing\SaleableInterface;
use Magento\Framework\View\Element\Template\Context;
use Ripen\Prophet21\Helper\WholesaleHelper;

class FinalPriceBox extends \Magento\Catalog\Pricing\Render\FinalPriceBox
{
    /**
     * @var WholesaleHelper
     */
    protected $wholesaleHelper;

    /**
     * @param Context $context
     * @param SaleableInterface $saleableItem
     * @param PriceInterface $price
     * @param RendererPool $rendererPool
     * @param array $data
     * @param SalableResolverInterface|null $salableResolver
     * @param MinimalPriceCalculatorInterface|null $minimalPriceCalculator
     * @param WholesaleHelper $wholesaleHelper
     */
    public function __construct(
        Context $context,
        SaleableInterface $saleableItem,
        PriceInterface $price,
        RendererPool $rendererPool,
        array $data = [],
        SalableResolverInterface $salableResolver = null,
        MinimalPriceCalculatorInterface $minimalPriceCalculator = null,
        WholesaleHelper $wholesaleHelper
    ) {
        parent::__construct($context, $saleableItem, $price, $rendererPool, $data, $salableResolver, $minimalPriceCalculator);

        $this->wholesaleHelper = $wholesaleHelper;
    }

    /**
     * Wrap with standard required container
     *
     * @param string $html
     * @return string
     */
    protected function wrapResult($html)
    {
        // do this only for wholesale website
        if ($this->wholesaleHelper->isLoggedInCustomerWholesale()) {
            // hidden class is used to facilitate toggle of pricing display on the frontend, such as Ripen_PricingToggle in Gallagher
            // @TODO: remove hidden class from this file, and add it directly in Gallagher repo in Ripen_PricingToggle possibly via plugin, as right now only Gallagher uses this
            // also adding inactive to hide initial retail price on wholesale (it gets removed by js/ajax-customer-prices.js once wholesale prices load)
            return '<div class="inactive price-box hidden ' . $this->getData('css_classes') . '" ' .
                'data-role="priceBox" ' .
                'data-product-id="' . $this->getSaleableItem()->getId() . '" ' .
                'data-price-box="product-id-' . $this->getSaleableItem()->getId() . '"' .
                '>' . $html . '</div>';
        }
        else {
            return parent::wrapResult($html);
        }
    }
}
