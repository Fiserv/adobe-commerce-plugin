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
            this.vaultEnabler = new VaultEnabler();

            this.isChecked = ko.computed({
                read: function () {
                    return checkoutData.getSelectedPaymentMethod();
                },
                write: function (value) {
                    checkoutData.setSelectedPaymentMethod(value);
                },
                owner: this
            });

            // Watch billing address changes to allow placing order
            quote.billingAddress.subscribe(function (address) {
                this.isPlaceOrderActionAllowed(address !== null);
            }, this);

            // Only render PayPal button and UI if PayPal is selected
            var selected = this.isChecked();
            if (selected === this.getCode()) {
                this.selectPaymentMethod();
            }

            return this;
        },

        /**
         * Select Payment Method Handler
         */
        selectPaymentMethod: function () {
            if (typeof this._super === 'function') {
                this._super();
            }
            this.isChecked(this.getCode()); 
            this.loadPayPalForm();
            if(this.isChecked()) {
                if(!this.paypalButtonRendered) {
                    this.loadPayPalForm();
                    this.paypalButtonRendered = true;
                }
            }
            return true;
        },

        isRadioButtonVisible: function () {
            return true;
        },

        getTitle: function () {
            var config = (window.checkoutConfig && window.checkoutConfig.payment) ? window.checkoutConfig.payment[this.getCode()] : {};
            return config && config.title ? config.title : 'PayPal';
        },

        isPlaceOrderActionAllowed: ko.observable(quote.billingAddress() != null),

        loadPayPalForm: function () {
            if (this.isChecked() === this.getCode()) {
                this.initPayPalAdapter();
            } else {
                console.warn('Payment method is NOT selected');
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

                paypalAdapter.initialize(config); 
                var credsResponse = await paypalSession({});
                var creds = credsResponse && credsResponse[paypalAdapter.credentialsKey];
                if (!creds) {
                    throw new Error('No credentials returned from PayPal session');
                }
                self.paymentPayload.sessionId = creds[paypalAdapter.sessionIdKey];
                await paypalAdapter.initSdk(config, creds);
                const paypalComponent = await paypalAdapter.loadPayPalComponent({
                    customerId: creds[paypalAdapter.customerIdKey],
                    intent: config[paypalAdapter.intentKey] || 'authorize'
                });
                const buttonsConfig = credsResponse && credsResponse.paypal_button_config ? credsResponse.paypal_button_config : {
            };
                await paypalAdapter.renderPayPalButtons(buttonsConfig);
            } catch (error) {
                globalMessageList.addErrorMessage({ message: $t('PayPal credentials error: ') + (error.message || error) });
                console.error('[PayPal] Error in initPayPalAdapter:', error);
            }
        },

        validatePayment: function() {
            var result = true;
            return result;
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

        /**
         * Get billing address
         *
         * @returns {String}
         */
        getBillingAddress: function () {
            let billingAddress = checkoutData.getBillingAddressFromData();
            if (!billingAddress) {
                billingAddress = quote.billingAddress();
            }
            return billingAddress;
        },

        showPrivacyStatement: function () {
            var config = (window.checkoutConfig && window.checkoutConfig.payment) ? window.checkoutConfig.payment[this.getCode()] : {};
            return config && config.show_privacy_statement;
        },

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
                    $('#venmo-button-container').empty();
                }
            }.bind(this));
        }
    });
});
