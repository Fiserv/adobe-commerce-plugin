define([
    'jquery',
    'Magento_Payment/js/view/payment/cc-form',
    'Magento_Ui/js/model/messageList',
    'Magento_Checkout/js/model/full-screen-loader',
    'Fiserv_Payments/js/ch-adapter',
    'Fiserv_Payments/js/action/create-commercehub-session',
    'Magento_Checkout/js/model/quote',
    'ko',
    'mage/translate'
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
            code: 'fiserv_applepay',
            additionalData: {},
            paymentPayload: {
                sessionId: null,
                orderId: null,
                type: 'applepay'
            },
            isPlaceOrderActionAllowed: ko.observable(false)
        },

        initialize: function () {
            this._super();
            this.observeBillingAddress();
            this.watchPaymentMethods();
            return this;
        },

        observeBillingAddress: function () {
            quote.billingAddress.subscribe((address) => {
                this.isPlaceOrderActionAllowed(address !== null);
            });
        },

        buildOrderData: function () {
            return quote.getItems().map(item => ({
                name: item.name,
                sku: item.sku,
                quantity: item.qty,
                amount: item.price
            }));
        },

        loadApplePayForm: async function () {
            const config = window.checkoutConfig.payment[this.getCode()];
            const sessionData = {
                customer: {
                    id: window.checkoutConfig.customerData?.id || 'guest'
                },
                billingAddress: quote.billingAddress(),
                orderData: this.buildOrderData()
            };

            try {
                fullScreenLoader.startLoader();
                const creds = await chSession(sessionData);
                if (!creds || !creds.ch_credentials) {
                    throw new Error('No credentials returned from backend');
                }

                this.paymentPayload.sessionId = creds.ch_credentials.sessionId;
                await chAdapter.initSdk(config, creds.ch_credentials);

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
                        onApprove: (data) => this.onApplePaySuccess(data.details, data),
                        onCancel: () => console.log('[ApplePay] Payment cancelled'),
                        onError: (error) => console.error('[ApplePay] SDK error:', error)
                    }
                });

            } catch (error) {
                console.error('[ApplePay] Initialization error:', error);
                globalMessageList.addErrorMessage({message: $t('Apple Pay error: ') + error.message});
            } finally {
                fullScreenLoader.stopLoader();
            }
        },

        watchPaymentMethods: function () {
            const self = this;
            $(`[name="payment[method]"]`).on("click", function () {
                const selected = $(this).attr("id");
                if (selected === self.getCode()) {
                    self.loadApplePayForm();
                }
            });
        },

        onApplePaySuccess: function (details, data) {
            const safeDetails = {
                applepay_order_id: data?.orderId,
                payment_source: data?.paymentMethod || 'applepay'
            };

            this.additionalData = {
                ...safeDetails,
                payment_session: this.paymentPayload.sessionId // ✅ aligned with DataAssignObserver
            };

            if (this.placeOrderCallback) {
                this.placeOrderCallback({
                    orderId: data.orderId,
                    email: quote.guestEmail || window.checkoutConfig.customerData?.email || 'guest@example.com',
                    merchantId: window.checkoutConfig.payment[this.getCode()].merchantId,
                    terminalId: window.checkoutConfig.payment[this.getCode()].terminalId
                });
            } else {
                console.error('No placeOrderCallback set for Apple Pay approval');
            }
        },

        placeOrderCallback: function (approvalData) {
            const self = this;
            self.additionalData.applepay_order_id = approvalData.orderId;

            require(['Magento_Checkout/js/action/place-order'], function (placeOrderAction) {
                placeOrderAction(self.getData()).done(function () {
                    window.location.href = '/checkout/onepage/success/';
                });
            });
        },

        getData: function () {
            return {
                method: this.getCode(),
                additional_data: {
                    payment_source: this.additionalData.payment_source,
                    applepay_order_id: this.additionalData.applepay_order_id,
                    payment_session: this.additionalData.payment_session // ✅ correct key
                }
            };
        },

        placeOrder: function () {
            window.location.href = '/checkout/onepage/success/';
        },

        getCode: function () {
            return this.code;
        },

        showPrivacyStatement: function () {
            const config = window.checkoutConfig.payment[this.getCode()];
            return config?.show_privacy_statement;
        },

        isBillingAddressRequired: function () {
            return true;
        },

        isActive: function () {
            const active = this.getCode() === this.isChecked();
            this.active(active);
            return active;
        }
    });
});