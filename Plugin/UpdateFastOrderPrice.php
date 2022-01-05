<?php

namespace Ripen\Prophet21\Plugin;

use Ripen\Prophet21\Exception\P21ApiException;

class UpdateFastOrderPrice
{
    /**
     * @var \Ripen\Prophet21\Model\Products
     */
    protected $p21Product;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;

    /**
     * @var \Magento\Catalog\Model\ProductFactory
     */
    protected $productloader;

    /**
     * @var \Magento\Framework\Pricing\Helper\Data
     */
    protected $priceHelper;

    /**
     * @param \Ripen\Prophet21\Model\Products $p21Product
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Catalog\Model\ProductFactory $productloader
     * @param \Magento\Framework\Pricing\Helper\Data $priceHelper
     */
    public function __construct(
        \Ripen\Prophet21\Model\Products $p21Product,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Catalog\Model\ProductFactory $productloader,
        \Magento\Framework\Pricing\Helper\Data $priceHelper
    )
    {
        $this->p21Product = $p21Product;
        $this->customerSession = $customerSession;
        $this->productloader = $productloader;
        $this->priceHelper = $priceHelper;
    }

    /**
     * Override default price with customer specific price
     *
     * @param \Mageants\FastOrder\Controller\Index\Search $subject
     */
    public function afterExecute(
        \Mageants\FastOrder\Controller\Index\Search $subject
    )
    {

        $items = [];

        $result = json_decode($subject->getResponse()->getBody());
        foreach ($result as $item) {

            $product = $this->productloader->create()->load($item->product_id);

            try {
                $customerPrice = current($this->p21Product->productCustomerPrices($this->customerSession->getCustomer()->getId(), [$product->getSku()]));
                if (!empty($customerPrice['unitPrice'])) {
                    $formattedPrice = $this->priceHelper->currency($customerPrice['unitPrice'], true, false);
                    $productPrice = $customerPrice['unitPrice'];
                } else {
                    $formattedPrice = $this->priceHelper->currency($product->getPrice(), true, false);
                    $productPrice = $product->getPrice();
                }
            } catch (P21ApiException $e) {
                $formattedPrice = 'Call for price';
                $productPrice = 0;
            }
            $item->product_price = $formattedPrice;
            $item->product_price_amount = $productPrice;
            $items[] = $item;
        }

        $subject->getResponse()->setBody(json_encode($result));
    }
}
