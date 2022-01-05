define([
    'jquery'
], function ($) {
    'use strict';

    function getMinChildPrice(childSkus, customerPrices) {
        var prices = [];
        Object.keys(customerPrices).forEach(function (index) {
            var sku = customerPrices[index].sku,
                unitPrice = customerPrices[index].unitPrice;

            if (!sku) return;

            if (childSkus.includes(sku)) {
                prices.push(unitPrice);
            }
        });

        if (prices.length > 0) {
            return Math.min(...prices);
        }
        return null;
    };

    return function () {

        var prices = $('.price-box .price'),
            itemNumbers = $('.product-item-number'),
            skus = [],
            childSkuInfo = {},
            productIds = {},
            apiUrl = '/rest/V1/feeds/products/prices/',
            $priceBoxes = $('.price-box'),
            $priceTiers = $('.prices-tier');

        // make sure there are prices present to be potentially replaced, as well as product item numbers to fetch SKUs from, before we proceed
        if (prices.length > 0 && itemNumbers.length > 0) {
            itemNumbers.each(function() {
                // we use jquery's attr method instead of the data method for sku to ensure we are getting a string sku.  
                // without using the attr method full numeric skus would be returned as integers and calls to toUpperCase would fail.
                var sku = $(this).attr('data-sku'),
                    productId = $(this).data('product-id'),
                    childSkuJson = $(this).find('.product-child-sku-json');

                if (typeof sku !== 'undefined') {
                    // normalize any sku we pull from the dom before it hits p21. p21 uses & returns fully capitalized skus.
                    // without this normalization our javascript was trying to match fully capitalized skus from p21 to whatever sku we had entered in magento. 
                    // if the sku in magento was mixed case it would cause customer pricing lookup to fail.
                    sku = sku.toUpperCase();

                    // queue up skus to look up
                    skus.push(sku);

                    // store the product id as well if present, will use as a fallback in case adding SKUs below fails due to lag
                    if (typeof productId !== 'undefined') {
                        productIds[sku] = productId;
                    }

                    // add sku as data attributes to correct prices so custom prices fetched below can be updated
                    // correctly

                    // Product category listing, Featured Products, My Favorites
                    $(this).parents('.product-item-info').find('.price-box .price').attr('data-sku',sku);

                    // Product detail page
                    $(this).parents('.product-info-main').find('.product-info-price .price-box .price').attr('data-sku',sku);

                    // Product Comparison
                    $(this).parents('.table-comparison').find('.js-product-info[data-sku='+sku+'] .price-box .price').attr('data-sku',sku);

                    // if this sku has child skus
                    if (childSkuJson.length > 0) {
                        var childSkus = JSON.parse(childSkuJson.html());
                        childSkuInfo[sku] = [];
                        Object.keys(childSkus).forEach(function (index) {
                            var childSku = childSkus[index]['sku'],
                                productId = childSkus[index]['id'];

                            if (typeof childSku !== 'undefined') {
                                // normalize the child sku here for the same reasons as above
                                childSku = childSku.toUpperCase();
                                // queue up skus to look up
                                skus.push(childSku);
                                // keep track of child sku information
                                childSkuInfo[sku].push(childSku);

                                if (typeof productId !== 'undefined') {
                                    productIds[childSku] = productId;
                                }
                            }
                        });
                    }
                }
            });

            // make sure we actually have SKUs to look up
            if (skus.length > 0) {
                // hide the current prices and trigger "loading" animation
                $priceBoxes.addClass('inactive');
                $priceTiers.addClass('inactive');

                $.ajax({
                    method: 'GET',
                    url: apiUrl + btoa(JSON.stringify(skus)),
                    cache: false,
                    contentType: "application/json",
                    dataType: "json",
                    success: function(customerPrices) {
                        if (typeof customerPrices !== 'object') {
                            console.error(apiUrl + ' did not return a valid JSON string');
                            handleApiError();
                            return false;
                        }

                        var formatter = new Intl.NumberFormat('en-US', {
                            style: 'currency',
                            currency: 'USD',
                        });

                        Object.keys(childSkuInfo).forEach(function (sku) {
                            var minChildPrice = getMinChildPrice(childSkuInfo[sku], customerPrices);

                            if (minChildPrice !== null) {
                                customerPrices.push({'sku': sku, 'unitPrice': minChildPrice, 'tierPrices': []});
                            }
                        });

                        Object.keys(customerPrices).forEach(function (index) {
                            var sku = customerPrices[index].sku,
                                unitPrice = customerPrices[index].unitPrice;

                            // do not replace price if no customer price found
                            if (! sku) return;

                            // TODO: Replace whole price block with a JS template rather than manipulating elements.
                            var formattedPrice = formatter.format(unitPrice),
                                product = $('#product-price-'+productIds[sku]),
                                price = product.find('.price'),
                                specialPrice = $('#product-price-'+productIds[sku]).closest('.special-price'),
                                oldPrice = $('#old-price-'+productIds[sku]+', .price-box[data-product-id='+productIds[sku]+'] .old-price'),
                                productItemNumber = $(".product-item-number[data-sku='" + sku + "']"),
                                productInfoPrice = productItemNumber.parents('.product-info-main').find('.product-info-price'),
                                priceBox = price.closest('.price-box'),
                                minimalPriceLink = priceBox.find('.minimal-price-link'),
                                tierPrices = customerPrices[index].tierPrices;

                            // update prices with customer pricing
                            specialPrice.removeClass('special-price');
                            oldPrice.hide();
                            price.html(formattedPrice);
                            product.attr('data-price-amount',unitPrice);

                            // if there are tiered prices or price breaks
                            if (tierPrices.length > 0) {
                                var tierBreak, formattedTierPrice;

                                // dynamically attach tier pricing info (product detail)
                                if (productInfoPrice.length > 0) {
                                    var ajaxTierHtml = '<div class="prices-tier items"><ul>';
                                    for (var i = 0; i < tierPrices.length; i++) {
                                        tierBreak = tierPrices[i].break;
                                        formattedTierPrice = formatter.format(tierPrices[i].price);

                                        ajaxTierHtml += '<li>Buy ' + tierBreak + '+ for ' + formattedTierPrice + '/ea</li>';
                                    }
                                    ajaxTierHtml += '</ul></div>';
                                    productInfoPrice.append(ajaxTierHtml);
                                }
                                // product listing pages with minimal price links loaded, this needs to come before the priceBox check
                                else if (minimalPriceLink.length > 0) {
                                    var minimalPriceTierHtml = '';
                                    for (var i = 0; i < tierPrices.length; i++) {
                                        tierBreak = tierPrices[i].break;
                                        formattedTierPrice = formatter.format(tierPrices[i].price);

                                        minimalPriceTierHtml += tierBreak + '+ ' + formattedTierPrice + '<br/>';
                                    }

                                    minimalPriceLink.html(minimalPriceTierHtml);
                                }
                                // product listing pages with no minimal price links loaded
                                else if (priceBox.length > 0) {
                                    var productUrl = priceBox.closest('.product-item-details').find('.product-item-number .product-item-link'),
                                        priceTierHtml = '';

                                    // fallback check for product link
                                    if (productUrl.length === 0) {
                                        productUrl = priceBox.closest('.product-item-info').find('.product-item-name .product-item-link');
                                    }

                                    if (productUrl.length > 0) {
                                        priceTierHtml = '<a href="' + productUrl.attr('href') + '" class="minimal-price-link">';
                                    }
                                    else {
                                        priceTierHtml = '<div class="minimal-price-link">';
                                    }
                                    for (var i = 0; i < tierPrices.length; i++) {
                                        tierBreak = tierPrices[i].break;
                                        formattedTierPrice = formatter.format(tierPrices[i].price);

                                        priceTierHtml += tierBreak + '+ ' + formattedTierPrice + '<br/>';
                                    }
                                    if (productUrl.length > 0) {
                                        priceTierHtml += '</a>';
                                    }
                                    else {
                                        priceTierHtml += '</div>';
                                    }

                                    priceBox.append(priceTierHtml);
                                }
                                // all other products with tier pricing
                                else {
                                    tierBreak = tierPrices[0].break;
                                    formattedTierPrice = formatter.format(tierPrices[0].price);

                                    minimalPriceLink.html(tierBreak + '+ ' + formattedTierPrice);
                                }
                            }
                            // no price breaks for this customer and sku, hide any existing as they don't apply to current customer
                            else {
                                minimalPriceLink.hide();
                            }
                        });

                        $priceBoxes.removeClass('inactive');
                        $priceTiers.removeClass('inactive');
                    },
                    error: function() {
                        console.error('Failed getting data from ' + apiUrl);
                        handleApiError();
                    }
                });
            }
        }
    };


    function handleApiError()
    {
        // replace and hide existing price as needed
        $('.price-container .price-wrapper').html('<span class="price-box call-to-order">Call to order</span>');
        $('.minimal-price-link').hide();
        $('.old-price').hide();

        // remove loader class
        $('.price-box').removeClass('inactive');
        $('.prices-tier').removeClass('inactive');

        // hide 'Add to Cart' form and buttons
        $('form[data-role=tocart-form], .product-item-info .actions-primary').hide();
        $('.product-add-form').find('form').hide();
        $('.box-tocart').find('fieldset').hide();
    }

});
