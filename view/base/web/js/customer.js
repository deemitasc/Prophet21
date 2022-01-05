/**
 * @api
 */
define([
    'underscore',
    'uiRegistry',
    'Magento_Ui/js/form/element/select'
], function (_, registry, Select) {
    'use strict';

    return Select.extend({
        defaults: {
            customerId: null,
            isGlobalScope: 0
        },

        /**
         * Website component constructor.
         * @returns {exports}
         */
        initialize: function () {
            this._super();

            // overrides Magento_Ui/js/form/element/website that was preventing website_id to be editable
            // when adding new customers if isGlobalScope == 1
            if (this.customerId) {
                this.disable(true);
            }

            return this;
        }
    });
});
