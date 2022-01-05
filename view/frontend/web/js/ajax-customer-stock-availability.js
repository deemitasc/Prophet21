define([
    'jquery',
    'Magento_Ui/js/modal/modal'
], function ($, modal) {
    'use strict';

    // from more recent version of underscore.js than is in magento
    function chunk(array, count) {
        if (count == null || count < 1) return [];
        var result = [];
        var i = 0, length = array.length;
        while (i < length) {
            result.push(array.slice(i, i += count));
        }
        return result;
    }

    return function (config) {
        var availabilityBoxes = $('.product-availability'),
            itemNumbers = $('.product-item-number'),
            productDetailPage = $('.catalog-product-view'),
            proceedToLoad = true,
            apiUrl = '/prophet21/inventory/availability',
            stockLocationsUrl = '/prophet21/inventory/locations',
            batchSize = 4,
            isCategoryPage = 0;

        // Don't use API calls on category page
        if ($('.page-products').length > 0){
            isCategoryPage = 1;
        }

        // make sure there are elements present to be potentially replaced, as well as product item numbers to fetch SKUs from, before we proceed
        if (availabilityBoxes.length > 0 && itemNumbers.length > 0) {
            var boxesToUpdate = {};

            availabilityBoxes.each(function() {
                if ($(this).parents('.product-info-main').length > 0){
                    var parentItemNumber = $(this).parents('.product-info-main').find('.product-item-number');
                } else {
                    var parentItemNumber = $(this).parents('.product-item-info').find('.product-item-number');
                }
                var sku = parentItemNumber.data('sku');

                if (typeof sku !== 'undefined') {
                    var availabilityBox = $(this),
                        childSkuJson = parentItemNumber.find('.product-child-sku-json');

                    // double check if the current availabilityBox is either js-catalog/js-catalog-inventory, and proceed if not so,
                    // works with the js-catalog/js-catalog-inventory check after the load block below to prevent duplicates, but also to not
                    // prevent normal availability boxes from loading
                    if (!proceedToLoad) {
                        if (! availabilityBox.hasClass('js-catalog') && ! availabilityBox.hasClass('js-catalog-inventory')) {
                            proceedToLoad = true;
                        }
                    }

                    if (proceedToLoad) {
                        if (config.shouldRefreshStockPostLoad) {
                            availabilityBox.removeClass('inactive');
                            availabilityBox.html('Checking availability');
                            availabilityBox.addClass('loading');
                        }

                        if (! (sku in boxesToUpdate)) boxesToUpdate[sku] = [];
                        boxesToUpdate[sku].push(availabilityBox);
                    }

                    // if on PDP, double check for duplicates
                    if (productDetailPage.length > 0) {
                        if (availabilityBox.hasClass('js-catalog') || availabilityBox.hasClass('js-catalog-inventory')) {
                            // if proceedToLoad is already false, then either js-catalog or js-catalog-inventory has already loaded first
                            if (!proceedToLoad && config.shouldRefreshStockPostLoad) {
                                // not the first occurence of js-catalog/js-catalog-inventory, so hide
                                availabilityBox.hide();
                            }
                            proceedToLoad = false;
                        }
                    }
                }
            });

            // TODO: Refactor batches to be in sequence of display on page so first items show first.
            var skuBatches = chunk(Object.keys(boxesToUpdate), batchSize);
            skuBatches.forEach(function (skuBatch) {
                if (config.shouldRefreshStockPostLoad) {
                    $.ajax({
                        method: 'GET',
                        url: apiUrl,
                        cache: false,
                        data: {skus: btoa(JSON.stringify(skuBatch)), isCategoryPage: isCategoryPage},
                        dataType: "json",
                        success: function (stockAvailability) {
                            if (!stockAvailability || typeof stockAvailability !== 'object') {
                                console.error(apiUrl + ' did not return valid data');
                                return;
                            }

                            Object.keys(stockAvailability).forEach(function (sku) {
                                boxesToUpdate[sku].forEach(function (availabilityBox) {
                                    var availabilityData = stockAvailability[sku];

                                    // update prices with customer pricing
                                    availabilityBox.html(availabilityData);

                                    availabilityBox.removeClass('inactive');
                                    availabilityBox.removeClass('loading');
                                });
                            });
                        },
                        error: function () {
                            console.error('Failed getting data from ' + apiUrl);
                            skuBatch.forEach(function (sku) {
                                boxesToUpdate[sku].forEach(function (availabilityBox) {
                                    availabilityBox.html('');
                                    availabilityBox.removeClass('inactive');
                                    availabilityBox.removeClass('loading');
                                });
                            });
                        }
                    });
                }
                if (config.shouldShowStockPopup) {
                    skuBatch.forEach(function (sku) {
                        $.ajax({
                            method: 'GET',
                            url: stockLocationsUrl,
                            cache: false,
                            data: { sku: btoa(sku) },
                            success: function (stockLocationsPopup) {
                                if (!stockLocationsPopup) {
                                    console.error(stockLocationsUrl + ' did not return valid data');
                                    return;
                                }
                                boxesToUpdate[sku].forEach(function (availabilityBox) {
                                    availabilityBox.after(stockLocationsPopup);
                                });
                            },
                            error: function () {
                                console.error('Failed getting data from ' + stockLocationsUrl);
                            }
                        });
                    });
                }
            });
        }
    };
});
