define([
    'jquery',
    'Magento_Catalog/js/price-utils'
], function ($, priceUtils) {
    'use strict';

    return function () {

        let priceContainer = $('.product-info-price .price-container.price-final_price'),
            uomSelector = $('.js-cart-uom');

        if (priceContainer.length > 0 && uomSelector.length > 0) {
            updatePrice(priceContainer, uomSelector.val());

            uomSelector.change(function() {
                updatePrice(priceContainer, $(this).val());
            });
        }

    };

    function updatePrice(priceContainer, UOMValue) {
        let priceWrapper = priceContainer.find('.price-wrapper'),
            basePrice = priceWrapper.attr('data-price-amount'),
            selectedUOMPair = UOMValue.split(':'),
            selectedUOM = selectedUOMPair[0],
            selectedUOMUnitSize = selectedUOMPair[1],
            updatedPrice = parseFloat(basePrice) * parseFloat(selectedUOMUnitSize);

        priceContainer.empty();
        priceWrapper.html('<span class="price">' + getFormattedPrice(updatedPrice) + '</span> / ' + selectedUOM);
        priceContainer.append(priceWrapper);
    }

    function getFormattedPrice(price) {
        return priceUtils.formatPrice(price);
    }

});
