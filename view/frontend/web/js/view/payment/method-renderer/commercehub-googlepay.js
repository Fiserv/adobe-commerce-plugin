define([
    'jquery',
    'Magento_Payment/js/view/payment/cc-form',
    'Magento_Ui/js/model/messageList',
    'Magento_Checkout/js/model/full-screen-loader',
    'Fiserv_Payments/js/ch-adapter',
    'Fiserv_Payments/js/action/create-commercehub-enriched-session',
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
            template: 'Fiserv_Payments/payment/commercehub/googlepay-form',
            code: 'fiserv_googlepay',
            additionalData: {},
            paymentPayload: {
                sessionId: null,
                orderId: null,
                type: 'googlepay',
                publicKeyHash: null
            },
            isPlaceOrderActionAllowed: ko.observable(false),
            initializedAmount: 0.00,
            googlepayComponent: null,
            googlepayInitialized: false
        },

        initialize: async function () {
            this._super();
            this.observeBillingAddress();
            return this;
        },

        initializeGooglePay: async function () {
            if (this.getCode() === this.isChecked()) {
                await this.loadGooglePayForm();
            }
            this.watchPaymentMethods();
        },

        observeBillingAddress: function () {
            quote.billingAddress.subscribe((address) => {
                this.isPlaceOrderActionAllowed(address !== null);
            });
        },

        observeTotals: function () {
            quote.totals.subscribe((totals) => {
                if (
                    this.getCode() === this.isChecked() &&
                    this.initializedAmount &&
                    this.initializedAmount > 0.00 &&
                    totals &&
                    totals['grand_total'] &&
                    totals['grand_total'] != this.initializedAmount
                ) {
                    location.reload();
                }
            });
        },

        loadGooglePayForm: async function () {
            const config = this.getPaymentConfig();

            try {
                if (this.googlepayInitialized && this.paymentPayload.sessionId) {
                    return;
                }

                fullScreenLoader.startLoader();
                $('#googlepay-button-container').empty();

                const creds = await chSession({ merchantName: config.storeName });
                if (!creds?.ch_credentials) {
                    throw new Error('Error creating payment session');
                }

                this.paymentPayload.sessionId = creds.ch_credentials.sessionId;
                await chAdapter.initSdk(config, creds.ch_credentials);

                await this.mountGooglePayComponent(config);
                this.initializedAmount = quote.totals()['grand_total'];
                this.observeTotals();

            } catch (error) {
                this.showError($t('Google Pay error: ') + (error?.message || error));
            } finally {
                fullScreenLoader.stopLoader();
            }
        },

        mountGooglePayComponent: async function (config) {
            const buttonColor = config.googlepayButtonStyle ?? 'black';
            const buttonType  = config.googlepayButtonType  ?? 'buy';
            const googlepayComponent = await window.fiserv.components.googlePay({
                data: {
                    button: {
                        parentElementId: 'googlepay-button-container',
                        color: buttonColor,
                        type:  buttonType
                    }
                },
                hooks: {
                    onApprove: (data) => {
                        this.onGooglePaySuccess(null, data);
                    },
                    onCancel: function () {},
                    onError: () => {
                        this.showError($t('Error during Google Pay checkout'));
                    }
                }
            });

            if (googlepayComponent) {
                this.googlepayComponent = googlepayComponent;
                this.googlepayInitialized = true;
                this.attachContainerClickGuards();
            }
        },

        attachContainerClickGuards: function () {
            const stopClick = function (event) {
                event.stopPropagation();
                event.stopImmediatePropagation();
                event.preventDefault();
                return false;
            };
            try {
                $('#googlepay-button-container')
                    .off('click.fiservStop')
                    .on('click.fiservStop', stopClick);
                $('#googlepay-button-container *').on('click.fiservStop', stopClick);
            } catch (error) {
                return;
            }
        },

        getPaymentConfig: function () {
            return window.checkoutConfig.payment[this.getCode()];
        },

        showError: function (message) {
            globalMessageList.addErrorMessage({ message: message });
        },

        watchPaymentMethods: function () {
            $('[name="payment[method]"]').on('click', (event) => {
                this.googlepayInitialized = false;
                if (event.currentTarget.id === this.getCode()) {
                    this.loadGooglePayForm();
                }
            });
        },

        onGooglePaySuccess: function (details, data) {
            this.additionalData = {
                payment_source: data?.paymentMethod || 'googlepay',
                payment_session: this.paymentPayload.sessionId
            };
            this.placeOrder('parent');
        },

        getData: function () {
            return {
                method: this.getCode(),
                additional_data: {
                    payment_source: this.additionalData.payment_source,
                    payment_session: this.additionalData.payment_session
                }
            };
        },

        getCode: function () {
            return this.code;
        },

        showPrivacyStatement: function () {
            return this.getPaymentConfig()?.show_privacy_statement;
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

