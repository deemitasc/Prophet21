define([
    'uiComponent'
], function (Component) {
    'use strict';

    return Component.extend({
        defaults: {
            displayArea: 'after_details',
            template: 'Ripen_Prophet21/summary/item/details/uom'
        },

        /**
         * @param {Object} quoteItem
         * @return {String}
         */
        getValue: function (quoteItem) {
            let quoteItemData = window.checkoutConfig.quoteItemData,
                returnValue = '';

            Object.keys(quoteItemData).forEach(function (index) {
                if (quoteItemData[index]['item_id'] == quoteItem['item_id']) {
                    if (quoteItemData[index]['uom']) {
                        returnValue = 'Selected UOM: ' + quoteItemData[index]['uom'];
                    }
                }
            });

            return returnValue;
        }
    });
});
