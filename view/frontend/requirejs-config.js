var config = {
    config: {
        mixins: {
            'Magento_Customer/js/model/customer/address': {
                'Ripen_Prophet21/js/model/customer/address-mixin': true
            },
            'Magento_Checkout/js/model/checkout-data-resolver': {
                'Ripen_Prophet21/js/model/checkout/checkout-data-resolver-mixin': true
            },
        }
    }
};
