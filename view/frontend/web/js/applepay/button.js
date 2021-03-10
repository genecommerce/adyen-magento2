/*
 *                       ######
 *                       ######
 * ############    ####( ######  #####. ######  ############   ############
 * #############  #####( ######  #####. ######  #############  #############
 *        ######  #####( ######  #####. ######  #####  ######  #####  ######
 * ###### ######  #####( ######  #####. ######  #####  #####   #####  ######
 * ###### ######  #####( ######  #####. ######  #####          #####  ######
 * #############  #############  #############  #############  #####  ######
 *  ############   ############  #############   ############  #####  ######
 *                                      ######
 *                               #############
 *                               ############
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2017 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */
define([
        'jquery',
        'ko',
        'uiComponent',
        'Magento_Checkout/js/model/payment/additional-validators',
        'mage/storage',
        'mage/url',
        'Magento_Ui/js/model/messages',
        'Adyen_Payment/js/applepay/api',
        'mage/translate',
    ],
    function ($, ko, Component, additionalValidators, storage, url, Messages, Api, $t) {
        'use strict';
        var canMakeApplePayPayments = ko.observable(false);
        var applePayVersion = 6;
        return Component.extend({
            defaults: {
                id: null,
                quoteId: 0,
                displayName: null,
                actionSuccess: null,
                grandTotalAmount: 0,
                isLoggedIn: false,
                storeCode: "default"
            },

            isShowLegend: function () {
                return true;
            },
            setPlaceOrderHandler: function (handler) {
                this.placeOrderHandler = handler;
            },
            setValidateHandler: function (handler) {
                this.validateHandler = handler;
            },
            getCode: function () {
                return 'adyen_apple_pay';
            },
            isActive: function () {
                return true;
            },
            deviceSupported: function () {
                if (location.protocol != 'https:') {
                    console.warn("Braintree Apple Pay requires your checkout be served over HTTPS");
                    return false;
                }

                if ((window.ApplePaySession && ApplePaySession.canMakePayments()) !== true) {
                    console.warn("Braintree Apple Pay is not supported on this device/browser");
                    return false;
                }

                return true;
            },
            initialize: function (config) {
                if (this.deviceSupported() === false) {
                    return;
                }
                var el = document.getElementById(config.id);
                $(el).addClass('apple-pay-button-with-text').addClass('apple-pay-button-black-with-text').append('<span class="logo"></span>');
                el.addEventListener('click', function (e) {
                    event.preventDefault();

                    var context = new Api();
                    context.setGrandTotalAmount(parseFloat(config.grandTotalAmount).toFixed(2));
                    context.setQuoteId(config.quoteId);
                    context.setActionSuccess(config.actionSuccess);
                    context.setIsLoggedIn(config.isLoggedIn);
                    context.setStoreCode(config.storeCode);
                    context.setDisplayName($t('Grand Total'));

                    var self = this;
                    if (!additionalValidators.validate()) {
                        return false;
                    }
                    var request = {
                        countryCode: config.countryCode,
                        currencyCode: config.currency,
                        supportedNetworks: ['visa', 'masterCard', 'amex', 'discover', 'maestro', 'vPay', 'jcb', 'elo'],
                        merchantCapabilities: ['supports3DS'],
                        total: {label: $t('Grand Total'), amount: parseFloat(config.grandTotalAmount).toFixed(2)},
                        requiredShippingContactFields: ['postalAddress', 'name', 'email', 'phone'],
                        requiredBillingContactFields: ['postalAddress', 'name']
                    };
                    var session = new ApplePaySession(applePayVersion, request);




                    session.onpaymentauthorized = function (event) {
                        context.startPlaceOrder('ads', event, session);
                    };


                    if (typeof context.onShippingContactSelect === 'function') {
                        session.onshippingcontactselected = function (event) {
                            return context.onShippingContactSelect(event, session);
                        };
                    }

                    // Attach onShippingMethodSelect method
                    if (typeof context.onShippingMethodSelect === 'function') {
                        session.onshippingmethodselected = function (event) {
                            return context.onShippingMethodSelect(event, session);
                        };
                    }

                    session.begin();
                }.bind(this));

            },
            context: function () {
                return this;
            },
            validate: function () {
                return true;
            },
            isApplePayAllowed: function () {
                if (!!window.ApplePaySession) {
                    if (window.ApplePaySession && window.ApplePaySession.supportsVersion(applePayVersion)) {
                        canMakeApplePayPayments(true);
                        return true;
                    }
                }
                return false;
            },
            isApplePayVisible: function () {
                return canMakeApplePayPayments();
            },

        });
    }
);
