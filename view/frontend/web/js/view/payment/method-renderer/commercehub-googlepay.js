define([
    'jquery',
    'Magento_Payment/js/view/payment/cc-form',
    'Magento_Ui/js/model/messageList',
    'Magento_Checkout/js/model/full-screen-loader',
    'Fiserv_Payments/js/ch-adapter',
    'Fiserv_Payments/js/action/create-commercehub-enriched-session',
    'Magento_Checkout/js/model/quote',
    'Fiserv_Payments/js/model/googlepay-locale',
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
    googlePayLocale,
    ko,
    $t
) {

    return Component.extend({
        defaults: {
            template: 'Fiserv_Payments/payment/commercehub/googlepay-form',
            code: 'fiserv_googlepay',
            additionalData: {},
            paymentPayload: {
                sessionId: null,
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

                const creds = await chSession({ merchantName: config.storeName, paymentCode: this.getCode() });
                if (!creds?.ch_credentials) {
                    throw new Error('Error creating payment session');
                }

                this.paymentPayload.sessionId = creds.ch_credentials.sessionId;
                await chAdapter.initSdk(config, creds.ch_credentials);
                await this.applySdkLocale(config);

                await this.mountGooglePayComponent(config);
                this.initializedAmount = quote.totals()['grand_total'];
                this.observeTotals();

            } catch (error) {
                this.showError($t('Google Pay error: ') + (error?.message || error));
            } finally {
                fullScreenLoader.stopLoader();
            }
        },

        /**
         * Force the CommerceHub SDK session's locale, which is what actually
         * drives the rendered language of the Google Pay button (and other
         * SDK-rendered wallet buttons/text).
         *
         * IMPORTANT (reverse-engineered from the shipped `checkout.js`
         * bundle — there is no published SDK reference for this):
         * `window.fiserv.components.googlePay({ data: { button: {...} } })`
         * does NOT read any per-call locale/buttonLocale field we pass in.
         * The button's language is always read from the SDK's internal
         * `checkoutContext.locale` (`config.customer.locale`), which is
         * normally seeded server-side during session creation. The only
         * supported client-side way to override it is the context's
         * `setLocale()` method, which must be called with one of the SDK's
         * exact supported BCP47 tags (see `Fiserv_Payments/js/model/googlepay-locale`)
         * — passing an unsupported/malformed tag throws, so this is wrapped
         * defensively and simply falls back to the SDK's default (English)
         * on failure.
         *
         * @param {Object} config
         * @returns {Promise<void>}
         */
        applySdkLocale: async function (config) {
            const rawLocale = config.locale || document.documentElement.lang || navigator.language;
            const sdkLocale = googlePayLocale.mapToSdkLocale(rawLocale);

            if (!window.fiserv || typeof window.fiserv.context !== 'function') {
                return;
            }

            try {
                const context = window.fiserv.context();

                if (context && typeof context.setLocale === 'function') {
                    await context.setLocale(sdkLocale);
                }
            } catch (error) {
                // Unsupported/malformed locale for this SDK version - SDK keeps
                // rendering in its default (English). Not fatal to checkout.
                // eslint-disable-next-line no-console
                console.warn('[GooglePay] Unable to set SDK locale to ' + sdkLocale + ':', error);
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
                        type:  buttonType,
                        buttonSizeMode: 'fill'
                    }
                },
                hooks: {
                    onApprove: (data) => {
                        this.onGooglePaySuccess(null, data);
                    },
                    onCancel: function () {
                    },
                    onError: () => {
                        this.showError($t('Error during Google Pay checkout'));
                    }
                }
            });

            if (googlepayComponent) {
                this.googlepayComponent = googlepayComponent;
                this.googlepayInitialized = true;
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

