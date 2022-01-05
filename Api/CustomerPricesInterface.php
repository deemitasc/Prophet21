<?php

namespace Ripen\Prophet21\Api;

interface CustomerPricesInterface
{
    /**
     * Get customer-specific pricing from P21 given specific customer IDs.
     *
     * @api
     * @param int $customerId Magento customer ID
     * @param string $skus
     * @return array
     */
    public function productCustomerPrices($customerId, $skus);
}
