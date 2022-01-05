define([
    'mage/utils/wrapper',
    'underscore'
], function(wrapper, _) {
    'use strict';

    return function(Address) {
        return wrapper.wrap(Address, function (_super, addressData) {
            let address = _super(addressData);
            let addressAttribute = _.findWhere(address.customAttributes, {
                attribute_code: 'prophet_21_id'
            });

            if (addressAttribute && addressAttribute.value != 0) {
                address.customerAddressId = null;
                address.getType = function () {
                    return 'prophet21-ship-to';
                };
                address.getKey = function () {
                    return this.getType() + addressAttribute.value;
                };
            }

            return address;
        });
    };
});
