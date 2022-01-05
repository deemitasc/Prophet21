define([
    'Magento_GoogleAnalytics/js/google-analytics',
    'Ripen_Prophet21/js/data/customer-code'
],
function (Component, customerCode) {
    'use strict';

    return function() {
        if (customerCode().getCode() && typeof ga !== 'undefined') {
            ga('set', 'dimension1', customerCode().getCode());
        }
    };
});
