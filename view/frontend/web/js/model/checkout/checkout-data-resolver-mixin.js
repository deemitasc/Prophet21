define([
    'underscore',
    'mage/utils/wrapper',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/action/select-shipping-method',
], function(_, wrapper, quote, selectShippingMethodAction) {
    'use strict';

    const p21CarrierMap = window.checkoutConfig.p21_shipping_carrier_map;

    return function(checkoutDataResolver) {
        checkoutDataResolver.resolveShippingRates = wrapper.wrapSuper(
            checkoutDataResolver.resolveShippingRates,
            function (ratesData) {
                this._super(ratesData);

                // If the quote already has a value set from the original method, don't do anything.
                if (quote.shippingMethod()) {
                    return;
                }

                const address = quote.shippingAddress();

                if (typeof address.customAttributes === 'undefined') {
                    return;
                }

                const p21CarrierIdAttr = _.findWhere(address.customAttributes, {
                    attribute_code: 'p21_default_carrier_id'
                });

                if (! p21CarrierIdAttr || ! p21CarrierIdAttr.value || ! p21CarrierMap[p21CarrierIdAttr.value]) {
                    return;
                }

                const method = _.find(ratesData, function (rate) {
                    return _.contains(p21CarrierMap[p21CarrierIdAttr.value], rate['carrier_code'] + '_' + rate['method_code']);
                });

                if (method) {
                    selectShippingMethodAction(method);
                }
            }
        );

        return checkoutDataResolver;
    };
});
