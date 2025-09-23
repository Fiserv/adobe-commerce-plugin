/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define([
    'jquery',
    'Magento_Payment/js/view/payment/cc-form',
    'Fiserv_Payments/js/paypal-adapter',
    'Fiserv_Payments/js/action/create-paypal-session',
    'Magento_Checkout/js/model/quote',
    'Magento_Ui/js/model/messageList',
    'Magento_Vault/js/view/payment/vault-enabler',
    'Magento_Checkout/js/checkout-data',
    'ko',
    'mage/translate',
    'domReady!'
], function (
    $,
    Component,
    paypalAdapter,
    paypalSession,
    quote,
    globalMessageList,
    VaultEnabler,
    checkoutData,
    ko,
    $t
) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Fiserv_Payments/payment/commercehub/paypal-form',
            code: 'fiserv_paypal',
            active: false,
            sortOrder: 20,
            paymentPayload: {
                sessionId: null,
                type: null,
                orderID: null
            },
            additionalData: {},
            paymentMethodName: '[name="payment[method]"]',
            credentials: undefined,
            isChecked: ko.observable()
        },

        initialize: function () {
            this._super();
            return checkoutData.getSelectedPaymentMethod() === this.getCode();
        },

        /**
         * Select Payment Method Handler
         */
        selectPaymentMethod: function () {
            this.isChecked(this.getCode());
            this.loadPayPalForm();
            return true;
        },

        // isPlaceOrderActionAllowed: ko.observable(quote.billingAddress() != null),

        loadPayPalForm: function () {
            if (this.isChecked() === this.getCode()) {
                this.initPayPalAdapter();
            } 
        },

    initPayPalAdapter: async function () {
            var self = this;
            var config = (window.checkoutConfig && window.checkoutConfig.payment) ? window.checkoutConfig.payment[self.getCode()] : {};
            try {
                if (!window.fiserv || typeof window.fiserv.components.paypal !== 'function') {
                    globalMessageList.addErrorMessage({ message: $t('Fiserv SDK is not loaded. Please check your network and configuration.') });
                    console.error('[PayPal] Fiserv SDK not loaded or window.fiserv.components.paypal not available');
                    return;
                }

                // Ensure customer data is loaded before proceeding
                await this.ensureCustomerDataLoaded();

                paypalAdapter.initialize(config);
                var credsResponse, creds;

                credsResponse = await paypalSession({});
                creds = credsResponse && credsResponse[paypalAdapter.credentialsKey];
                if (!creds) {
                    throw new Error('No credentials returned from PayPal session');
                }

                self.paymentPayload.sessionId = creds[paypalAdapter.sessionIdKey];
                await paypalAdapter.initSdk(config, creds);
                var intent = 'authorize';
                if (config.payment_action.toLowerCase() === 'authorize_capture') {
                    intent = 'capture';
                }
                const paypalComponent = await paypalAdapter.loadPayPalComponent({
                    intent: intent
                });
                const buttonsConfig = credsResponse && credsResponse.paypal_button_config ? credsResponse.paypal_button_config : {};
                await paypalAdapter.renderPayPalButtons(buttonsConfig);
            } catch (error) {
                globalMessageList.addErrorMessage({ message: $t('PayPal credentials error: ') + (error.message || error) });
                console.error('[PayPal] Error in initPayPalAdapter:', error);
            }
        },


        getData: function () {
            var data = {
                method: this.getCode(),
                additional_data: this.additionalData || {}
            };
            if (this.paymentPayload && this.paymentPayload.sessionId) {
                data.additional_data.payment_session = this.paymentPayload.sessionId;
            }
            return data;
        },

        isVaultEnabled: function () {
            return this.vaultEnabler && this.vaultEnabler.isVaultEnabled();
        },


        // isVenmoEnabled: function () {
        //     var config = (window.checkoutConfig && window.checkoutConfig.payment) ? window.checkoutConfig.payment[this.getCode()] : {};
        //     return config && config.venmoConfig && config.venmoConfig.enableVenmo;
        // },

        /**
         * Check if payment is active
         *
         * @returns {Boolean}
         */
        isActive: function () {
            let active = this.getCode() === this.isChecked();
            this.active(active);
            return active;
        },

        getCode: function () {
            return this.code;
        },

        watchPaymentMethods: function(element) {
            $(element).on('click', function() {
                if (this.getCode() !== this.isChecked()) {
                    // Clear PayPal and Venmo button containers when switching away
                    $('#paypal-button-container').empty();
                    // $('#venmo-button-container').empty();
                }
            }.bind(this));
        },

        /**
         * Ensure customer data is loaded before PayPal initialization
         */
        ensureCustomerDataLoaded: async function() {
            var self = this;
            var maxRetries = 10;
            var retryCount = 0;
            var retryDelay = 500; // 500ms

            return new Promise(function(resolve, reject) {
                function checkCustomerData() {
                    retryCount++;

                    // Check if customer data is available in checkout config
                    var config = window.checkoutConfig || {};
                    var paymentConfig = config.payment || {};
                    var paypalConfig = paymentConfig[self.getCode()] || {};

                    // For logged-in users, ensure customer data is loaded
                    if (config.isCustomerLoggedIn && config.customerData) {
                        if (config.customerData.email) {
                            resolve();
                            return;
                        }
                    }

                    // For guest users, check if billing address has email
                    if (!config.isCustomerLoggedIn && quote && quote.billingAddress && quote.billingAddress._latestValue && quote.billingAddress._latestValue.email) {
                        resolve();
                        return;
                    }

                    // If we've exceeded max retries, resolve anyway to prevent infinite waiting
                    if (retryCount >= maxRetries) {
                        resolve();
                        return;
                    }

                    // Wait and retry
                    setTimeout(checkCustomerData, retryDelay);
                }

                // Start checking immediately
                checkCustomerData();
            });
        }
    });
});
