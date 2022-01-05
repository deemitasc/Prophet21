<?php

/**
 * Plugin to assess whether or not an order being created can bypass the minimum order amount validation,
 * such as historical orders import or P21 orders created outside of Magento that's being synchronized
 */

namespace Ripen\Prophet21\Plugin;


use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote;

class MinimumOrderAmountBypass
{
    public function afterValidateMinimumAmount(
        Address $subject,
        $result
    ) {
        if ($result === false) {
            $quote = $subject->getQuote();

            $remoteIp = $quote->getData('remote_ip');

            if (is_null($remoteIp)) {
                $result = true;
            }
        }

        return $result;
    }
}
