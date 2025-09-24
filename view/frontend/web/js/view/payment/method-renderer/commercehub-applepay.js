define([
    'jquery',
    'Magento_Payment/js/view/payment/cc-form',
    'Magento_Ui/js/model/messageList',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Checkout/js/action/place-order',
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
    placeOrderAction,
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
            console.log('[ApplePay] Initializing payment method.');
            this.observeBillingAddress();
            this.watchPaymentMethods();
            return this;
        },

        observeBillingAddress: function () {
            quote.billingAddress.subscribe((address) => {
                console.log('[ApplePay] Billing address changed:', address);
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
            console.log('[ApplePay] Loading Apple Pay form...');
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

                console.log('[ApplePay] Apple Pay button rendered.');
            } catch (error) {
                console.error('[ApplePay] Initialization error:', error);
                globalMessageList.addErrorMessage({ message: $t('Apple Pay error: ') + error.message });
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
            console.log('[ApplePay] Payment approved:', details, data);

            const safeDetails = {
                applepay_order_id: data?.orderId,
                payment_source: data?.paymentMethod || 'applepay',
                applepay_details: details
            };

            this.additionalData = safeDetails;

            const payload = {
                sessionId: this.paymentPayload.sessionId,
                email: quote.guestEmail || window.checkoutConfig.customerData?.email || 'guest@example.com',
                action: 'authorize',
                applepay_details: safeDetails
            };

            fetch('/fiserv/applepay/validate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(payload),
                credentials: 'same-origin'
            })
                .then(async (res) => {
                    const text = await res.text();
                    let response;

                    try {
                        response = JSON.parse(text);
                    } catch (jsonError) {
                        console.error('[ApplePay] Invalid JSON from backend:', jsonError);
                        globalMessageList.addErrorMessage({ message: $t('Invalid response from Apple Pay backend.') });
                        return;
                    }

                    if (response.success) {
                        this.placeOrder();
                    } else {
                        globalMessageList.addErrorMessage({ message: $t(response.message || 'Authorization failed.') });
                    }
                })
                .catch((error) => {
                    console.error('[ApplePay] Backend call failed:', error);
                    globalMessageList.addErrorMessage({ message: $t('Apple Pay backend error: ') + error.message });
                });
        },

        getData: function () {
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
            if (!this.isPlaceOrderActionAllowed()) {
                globalMessageList.addErrorMessage({
                    message: $t('Please enter a valid billing address before placing the order.')
                });
                return;
            }

            const data = this.getData();
            fullScreenLoader.startLoader();

            placeOrderAction(data, this.messageContainer)
                .done(() => {
                    console.log('[ApplePay] Order placed successfully.');
                })
                .fail((response) => {
                    console.error('[ApplePay] Order placement failed:', response);
                    globalMessageList.addErrorMessage({
                        message: $t('Order placement failed. Please try again.')
                    });
                })
                .always(() => {
                    fullScreenLoader.stopLoader();
                });
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
