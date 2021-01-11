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
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/model/url-builder',
        'mage/storage',
        'mage/url',
        'Magento_Ui/js/model/messages',
        'Adyen_Payment/js/applepay/api',
        'mage/translate',
    ],
    function ($, ko, quote, Component, placeOrderAction, additionalValidators, urlBuilder, storage, url, Messages, Api, $t) {
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


                    var self = this;
                    if (!additionalValidators.validate()) {
                        return false;
                    }
                    var request = {
                        countryCode: 'US',
                        currencyCode: quote.totals().quote_currency_code,
                        supportedNetworks: ['visa', 'masterCard', 'amex', 'discover', 'maestro', 'vPay', 'jcb', 'elo'],
                        merchantCapabilities: ['supports3DS'],
                        total: {label: $t('Grand Total'), amount: quote.totals().base_grand_total}
                    };
                    var session = new ApplePaySession(applePayVersion, request);

                    if (typeof context.onShippingContactSelect === 'function') {
                        session.onshippingcontactselected = function (event) {
                            console.log(123);
                            return context.onShippingContactSelect(event, session);
                        };
                    }

                    // Attach onShippingMethodSelect method
                    if (typeof context.onShippingMethodSelect === 'function') {
                        session.onshippingmethodselected = function (event) {
                            console.log(12322);
                            return context.onShippingMethodSelect(event, session);
                        };
                    }
                    session.onvalidatemerchant = function (event) {
                        var promise = self.performValidation(event.validationURL);
                        promise.then(function (merchantSession) {
                            session.completeMerchantValidation(merchantSession);
                        });
                    }

                    session.onpaymentauthorized = function (event) {
                        var data = {
                            'method': self.item.method,
                            'additional_data': {'token': JSON.stringify(event.payment)}
                        };
                        var promise = self.sendPayment(event.payment, data);

                        promise.then(function (success) {
                            var status;
                            if (success) {
                                status = ApplePaySession.STATUS_SUCCESS;
                            } else {
                                status = ApplePaySession.STATUS_FAILURE;
                            }

                            session.completePayment(status);

                            if (success) {
                                window.location.replace(url.build(window.checkoutConfig.payment[quote.paymentMethod().method].redirectUrl));
                            }
                        }, function (reason) {
                            if (reason.message == "ERROR BILLING") {
                                var status = session.STATUS_INVALID_BILLING_POSTAL_ADDRESS;
                            } else if (reason.message == "ERROR SHIPPING") {
                                var status = session.STATUS_INVALID_SHIPPING_POSTAL_ADDRESS;
                            } else {
                                var status = session.STATUS_FAILURE;
                            }
                            session.completePayment(status);
                        });
                    };


                    // Attach onShippingContactSelect method
                    if (typeof context.onShippingContactSelect === 'function') {
                        session.onshippingcontactselected = function (event) {
                            console.log(123);
                            //return context.onShippingContactSelect(event, session);
                        };
                    }

                    // Attach onShippingMethodSelect method
                    if (typeof context.onShippingMethodSelect === 'function') {
                        session.onshippingmethodselected = function (event) {
                            console.log(12322);
                            return context.onShippingMethodSelect(event, session);
                        };
                    }

                    session.begin();
                }.bind(this));

            },
            getControllerName: function () {
                return window.checkoutConfig.payment.iframe.controllerName[this.getCode()];
            },
            getPlaceOrderUrl: function () {
                return window.checkoutConfig.payment.iframe.placeOrderUrl[this.getCode()];
            },
            context: function () {
                return this;
            },
            validate: function () {
                return true;
            },
            showLogo: function () {
                return window.checkoutConfig.payment.adyen.showLogo;
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
            performValidation: function (validationURL) {
                // Return a new promise.
                return new Promise(function (resolve, reject) {

                    // retrieve payment methods
                    var serviceUrl = urlBuilder.createUrl('/adyen/request-merchant-session', {});

                    storage.post(
                        serviceUrl,
                        JSON.stringify('{}')
                    ).done(
                        function (response) {
                            var data = JSON.parse(response);
                            resolve(data);
                        }
                    ).fail(function (error) {
                        console.log(JSON.stringify(error));
                        reject(Error("Network Error"));
                    });
                });
            },
            sendPayment: function (payment, data) {
                var deferred = $.Deferred();
                return $.when(
                    placeOrderAction(data, new Messages())
                ).fail(
                    function (response) {
                        deferred.reject(Error(response));
                    }
                ).done(
                    function () {
                        deferred.resolve(true);
                    }
                );
            },
            isApplePayVisible: function () {
                return canMakeApplePayPayments();
            },
            getMerchantIdentifier: function () {
                return window.checkoutConfig.payment.adyen_apple_pay.merchant_identifier;
            }
        });
    }
);
