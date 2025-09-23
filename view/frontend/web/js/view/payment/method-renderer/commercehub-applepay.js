define([
    'jquery',
    'Magento_Payment/js/view/payment/cc-form',
    'Magento_Ui/js/model/messageList',
    'Magento_Checkout/js/model/full-screen-loader',
    'Fiserv_Payments/js/ch-adapter',
    'Fiserv_Payments/js/action/create-commercehub-session',
    'Magento_Checkout/js/model/quote',
    'ko',
    'mage/translate',
], function (
    $,
    Component,
    globalMessageList,
    fullScreenLoader,
    chAdapter,
    chSession,
    quote,
    ko,
    $t
) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Fiserv_Payments/payment/commercehub/applepay-form',
            active: false,
            code: 'fiserv_applepay',
            additionalData: {},
            paymentPayload: {
                sessionId: null,
                orderId: null,
                type: 'applepay'
            },
            paymentMethodName: '[name="payment[method]"',
        },

        initialize: function () {
            console.log('[ApplePay] Initializing Apple Pay payment method.');
            this._super();
            this.observeBillingAddress();
            return this;
        },

        observeBillingAddress: function () {
            console.log('[ApplePay] Subscribing to billing address changes.');
            quote.billingAddress.subscribe((address) => {
                console.log('[ApplePay] Billing address changed:', address);
                this.isPlaceOrderActionAllowed(address !== null);
            });
        },

        isPlaceOrderActionAllowed: ko.observable(quote.billingAddress() !== null),

        buildOrderData: function () {
            const items = quote.getItems().map(item => ({
                name: item.name,
                sku: item.sku,
                quantity: item.qty,
                amount: item.price
            }));

            return items;
        },

        loadApplePayForm: async function () {
            console.log('[ApplePay] Starting Apple Pay form load.');
            const config = window.checkoutConfig.payment[this.getCode()];
            console.log('[ApplePay] Checkout config:', config);

            try {
                fullScreenLoader.startLoader();
                console.log('[ApplePay] Requesting credentials from backend.');

                const sessionData = {
                    customer: { id: window.checkoutConfig.customerData?.id || 'guest' },
                    billingAddress: quote.billingAddress(),
                    orderData: this.buildOrderData()
                };

                console.log('[ApplePay] Session data being sent:', sessionData);
                const creds = await chSession(sessionData);
                console.log('[ApplePay] Credentials response:', creds);

                if (!creds || !creds.ch_credentials) {
                    throw new Error('No credentials returned from Apple Pay session');
                }

                this.paymentPayload.sessionId = creds.ch_credentials.sessionId;
                console.log('[ApplePay] Session ID set:', this.paymentPayload.sessionId);

                console.log('[ApplePay] Initializing Fiserv SDK...');
                await chAdapter.initSdk(config, creds.ch_credentials);

                console.log('[ApplePay] Rendering Apple Pay button...');
                await window.fiserv.components.applePay({
                    data: {
                        button: {
                            parentElementId: "applepay-button-container",
                            color: 'black',
                            type: 'buy',
                            locale: "en-US"
                        }
                    },
                    hooks: {
                        onApprove: (data) => {
                            console.log('[ApplePay] onApprove triggered with data:', data);
                            this.onApplePaySuccess(data.details, data);
                        },
                        onCancel: () => {
                            console.log('[ApplePay] onCancel triggered');
                        },
                        onError: (error) => {
                            console.error('[ApplePay] onError triggered:', error);
                        }
                    }
                });

                console.log('[ApplePay] Apple Pay button rendered successfully.');
                fullScreenLoader.stopLoader();
            } catch (error) {
                fullScreenLoader.stopLoader();
                console.error('[ApplePay] Error during Apple Pay initialization:', error);
                globalMessageList.addErrorMessage({
                    message: $t('Apple Pay error: ') + (error.message || error)
                });
            }
        },

        watchPaymentMethods: function () {
            let self = this;
            $(self.paymentMethodName).on("click", function () {
                let selected = $(this).attr("id");
                if (selected === self.getCode()) {
                    self.loadApplePayForm();
                } else {
                    console.log("WOW Tyson is ugly");
                }
            });
        },

        onApplePaySuccess: function (details, data) {
            console.log('[ApplePay] Apple Pay success callback triggered.');
            console.log('[ApplePay] Details:', details);
            console.log('[ApplePay] Data:', data);

            this.additionalData = {
                applepay_order_id: data?.orderId,
                payment_source: data?.paymentMethod || 'applepay',
                applepay_details: details
            };

            console.log('[ApplePay] Additional data set:', this.additionalData);
            this.placeOrder();
        },

        getData: function () {
            console.log('[ApplePay] Preparing payment data for submission.');
            const data = {
                method: this.getCode(),
                additional_data: {
                    ...this.additionalData
                }
            };

            if (this.paymentPayload.sessionId) {
                data.additional_data.payment_session = this.paymentPayload.sessionId;
            }

            console.log('[ApplePay] Final payment data:', data);
            return data;
        },

        placeOrder: function () {
            console.log('[ApplePay] placeOrder() called.');

            if (!this.isPlaceOrderActionAllowed()) {
                console.warn('[ApplePay] Place order not allowed. Billing address may be missing.');
                globalMessageList.addErrorMessage({
                    message: $t('Please enter a valid billing address before placing the order.')
                });
                return;
            }

            const data = this.getData();
            console.log('[ApplePay] Data being submitted to Magento:', data);

            return this._super();
        },

        getCode: function () {
            console.log('[ApplePay] Returning payment method code:', this.code);
            return this.code;
        },

        showPrivacyStatement: function () {
            const config = window.checkoutConfig.payment[this.getCode()];
            console.log('[ApplePay] Privacy statement visibility:', config?.show_privacy_statement);
            return config?.show_privacy_statement;
        },

        isBillingAddressRequired: function () {
            console.log('[ApplePay] Billing address is required.');
            return true;
        },

        isActive: function () {
            const active = this.getCode() === this.isChecked();
            console.log('[ApplePay] Is payment method active?', active);
            this.active(active);
            return active;
        },
    });
});
