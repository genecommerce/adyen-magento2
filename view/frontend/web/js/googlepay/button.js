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
        'uiComponent',
        'ko',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Ui/js/model/messages',
        'Adyen_Payment/js/form-builder',
        'Magento_Vault/js/view/payment/vault-enabler',
        'gplibrary',
        'adyenCheckout',
        'mage/translate',
    ],
    function ($, Component, ko, fullScreenLoader, additionalValidators, Messages, formBuilder, VaultEnabler, gplibrary, AdyenCheckout, $t) {
        'use strict';
        var canMakeApplePayPayments = ko.observable(false);
        var applePayVersion = 6;
        return Component.extend({
            defaults: {
                id: null,
                quoteId: 0,
                displayName: null,
                grandTotalAmount: 0,
                isLoggedIn: false,
                storeCode: "default",
                googlePayToken: null,
                googlePayAllowed: null,
                actionSuccess: null
            },

            initialize: function (config) {
                if (!this.deviceSupported()) {
                    return;
                }
                this.setActionSuccess(config.actionSuccess)
                this.googlePayNode = document.getElementById(config.id);
                this.additionalValidators = additionalValidators;
                this.vaultEnabler = new VaultEnabler();
                this.vaultEnabler.setPaymentCode(this.getVaultCode());
                this.vaultEnabler.isActivePaymentTokenEnabler(false);
                this._super()
                    .observe([
                        'googlePayToken',
                        'googlePayAllowed'
                    ]);
                var self = this;
                self.checkoutComponent = new AdyenCheckout({
                    locale: config.locale,
                    originKey: config.originkey,
                    environment: config.checkoutenv,
                    risk: {
                        enabled: false
                    }
                });
                var googlepay = self.checkoutComponent.create('paywithgoogle', {
                    showPayButton: true,
                    environment: config.checkoutenv.toUpperCase(),
                    buttonColor: 'black', // default/black/white
                    buttonType: 'long', // long/short
                    showButton: true, // show or hide the Google Pay button
                    emailRequired: true,
                    shippingAddressRequired: true,
                    billingAddressRequired: true,
                    transactionInfo: {
                        totalPriceStatus: 'FINAL',
                        totalPrice: self.formatAmount(config.amount, config.format),
                        currencyCode: config.currency
                    },
                    allowedPaymentMethods: ['CARD'],
                    phoneNumberRequired: true,
                    cardRequirements: {
                        billingAddressRequired: true,
                        billingAddressFormat: 'FULL',
                    },
                    configuration: {
                        // Adyen's merchant account
                        gatewayMerchantId: config.merchantAccount,

                        // https://developers.google.com/pay/api/web/reference/object#MerchantInfo
                        merchantIdentifier: config.merchantIdentifier,
                        merchantName: config.merchantAccount
                    },

                    // Payment
                    amount: self.formatAmount(config.amount, config.format),
                    currency: config.currency,
                    totalPriceStatus: 'FINAL',

                    // empty onSubmit to resolve javascript issues.
                    onSubmit: function() {},
                    onChange: function (state) {
                        if (!state.isValid) {
                            fullScreenLoader.stopLoader();
                        }

                    },
                    onAuthorized: function (data) {
                        console.log(data);
                        var nonce = data.paymentMethodData.tokenizationData.token;
                        self.startPlaceOrder(nonce, data);
                    }
                });
                var promise = googlepay.isAvailable();
                promise.then(function (success) {
                    self.googlePayAllowed(true);
                    googlepay.mount(self.googlePayNode);
                    $(self.googlePayNode).find('button').prop('disabled', !self.validate(true));
                }, function (error) {
                    console.log(error);
                    self.googlePayAllowed(false);
                });
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
                return 'adyen_google_pay';
            },
            isActive: function () {
                return true;
            },

            startPlaceOrder: function (nonce, paymentData) {
                var payload = {
                    details: {
                        shippingAddress: {
                            streetAddress: paymentData.shippingAddress.address1 + "\n"
                                + paymentData.shippingAddress.address2,
                            locality: paymentData.shippingAddress.locality,
                            postalCode: paymentData.shippingAddress.postalCode,
                            countryCodeAlpha2: paymentData.shippingAddress.countryCode,
                            email: paymentData.email,
                            name: paymentData.shippingAddress.name,
                            telephone: typeof paymentData.shippingAddress.phoneNumber !== 'undefined' ? paymentData.shippingAddress.phoneNumber : '',
                            region: typeof paymentData.shippingAddress.administrativeArea !== 'undefined' ? paymentData.shippingAddress.administrativeArea : ''
                        },
                        billingAddress: {
                            streetAddress: paymentData.paymentMethodData.info.billingAddress.address1 + "\n"
                                + paymentData.paymentMethodData.info.billingAddress.address2,
                            locality: paymentData.paymentMethodData.info.billingAddress.locality,
                            postalCode: paymentData.paymentMethodData.info.billingAddress.postalCode,
                            countryCodeAlpha2: paymentData.paymentMethodData.info.billingAddress.countryCode,
                            email: paymentData.email,
                            name: paymentData.paymentMethodData.info.billingAddress.name,
                            telephone: typeof paymentData.paymentMethodData.info.billingAddress.phoneNumber !== 'undefined' ? paymentData.paymentMethodData.info.billingAddress.phoneNumber : '',
                            region: typeof paymentData.paymentMethodData.info.billingAddress.administrativeArea !== 'undefined' ? paymentData.paymentMethodData.info.billingAddress.administrativeArea : ''
                        }
                    },
                    nonce: nonce
                };

                formBuilder.build({
                    action: this.getActionSuccess(),
                    fields: {
                        result: JSON.stringify(payload)
                    }
                }).submit();
            },

            context: function () {
                return this;
            },
            validate: function (hideErrors) {
                return this.additionalValidators.validate(hideErrors);
            },
            /**
             * Set and get success redirection url
             */
            setActionSuccess: function (value) {
                this.actionSuccess = value;
            },
            getActionSuccess: function () {
                return this.actionSuccess;
            },
            /**
             * Get data for place order
             * @returns {{method: *}}
             */
            getData: function () {
                return {
                    'method': "adyen_google_pay",
                    'additional_data': {
                        'token': this.googlePayToken()
                    }
                };
            },

            /**
             * Return the formatted currency. Adyen accepts the currency in multiple formats.
             * @param $amount
             * @param $currency
             * @return string
             */
            formatAmount: function (amount, format) {
                return Math.round(amount * (Math.pow(10, format)))
            },
            isVaultEnabled: function () {
                return this.vaultEnabler.isVaultEnabled();
            },
            getVaultCode: function () {
                return "adyen_google_pay_vault";
            },
            onPaymentMethodContentChange: function (data, event) {
                $(this.googlePayNode).find('button').prop('disabled', !this.validate());
            },
            deviceSupported: function() {
                return !!(window.PaymentRequest);
            }

        });
    }
);
