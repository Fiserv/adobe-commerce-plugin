define([
    'jquery',
    'Fiserv_Payments/js/ch-adapter',
    'Fiserv_Payments/js/action/create-commercehub-enriched-session',
    'Magento_Checkout/js/model/quote',
    'Magento_Ui/js/model/messageList',
    'Magento_Checkout/js/checkout-data',
    'Magento_Checkout/js/model/full-screen-loader',
    'mage/translate',
], function (
    $,
    chAdapter,
    commercehubSession,
    quote,
    globalMessageList,
    checkoutData,
    fullScreenLoader,
    $t
) {
    'use strict';

    const DEFAULT_BUTTON_CONFIG = {
        paypal: {
            color: 'gold',
            shape: 'rect',
            label: 'paypal'
        },
        venmo: {
            color: 'blue',
            shape: 'rect'
        }
    };

    const PAYMENT_INTENTS = {
        AUTHORIZE: 'authorize',
        CAPTURE: 'capture'
    };

    const CUSTOMER_CONFIRMATION = {
        REVIEW_AND_PAY: 'REVIEW_AND_PAY',
        PAY_NOW: 'PAY_NOW'
    };

    const EMAIL_REGEX = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    /**
     * Mixin containing common methods for PayPal and Venmo payment methods
     */
    const PaymentMethodMixin = {
        /**
         * Get payment data for submission
         */
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

        /**
         * Check if payment method is active
         */
        isActive: function () {
            let active = this.getCode() === this.isChecked();
            this.active(active);
            return active;
        },

        /**
         * Initialize observable properties
         */
        initObservable: function () {
            this._super()
                .observe(['active']);
            return this;
        },

        /**
         * Watch for payment method selection changes
         */
        watchPaymentMethods: function () {
            let self = this;
            $(self.paymentMethodName).on("click", function () {
                let selected = $(this).attr("id");
                if (selected === self.getCode()) {
                    self.loadPayPalForm();
                } else {
                    $('#' + self.buttonContainerId).empty();
                }
            });
        }
    };

    return class PayPalVenmoBase {
        constructor(paymentCode, buttonContainerId) {
            this.code = paymentCode;
            this.buttonContainerId = buttonContainerId;
            this.paymentPayload = {
                sessionId: null,
                type: null,
                orderID: null
            };
            this.additionalData = {};
            this.paypalComponent = null;
        }

        /**
         * Initialize CommerceHub adapter and PayPal component
         */
        async initChAdapter() {
            const config = (window.checkoutConfig && window.checkoutConfig.payment) ? window.checkoutConfig.payment[this.code] : {};
            fullScreenLoader.startLoader();
            try {
                if (!window.fiserv || typeof window.fiserv.components.paypal !== 'function') {
                    globalMessageList.addErrorMessage({ message: $t('Fiserv SDK is not loaded. Please check your network and configuration.') });
                    console.error(`[${this.code}] Fiserv SDK not loaded or window.fiserv.components.paypal not available`);
                    return;
                }

                chAdapter.initialize(config);
                const credsResponse = await commercehubSession({});
                const creds = credsResponse && credsResponse[chAdapter.credentialsKey];
                if (!creds) {
                    throw new Error(`No credentials returned from ${this.code} session`);
                }

                this.paymentPayload.sessionId = creds[chAdapter.sessionIdKey];
                await chAdapter.initSdk(config, creds);

                const intent = PAYMENT_INTENTS.AUTHORIZE;
                if (config.payment_action && config.payment_action.toLowerCase() === 'authorize_capture') {
                    intent = PAYMENT_INTENTS.CAPTURE;
                }

                const paypalComponentConfig = { intent };
                this.paypalComponent = await chAdapter.loadPayPalComponent(paypalComponentConfig);
                const buttonsConfig = config.buttonConfig;
                await this.renderPayPalButtons(buttonsConfig);
            } catch (error) {
                globalMessageList.addErrorMessage({ message: $t(`${this.code} credentials error: `) + (error.message || error) });
                console.error(`[${this.code}] Error in initChAdapter:`, error);
            } finally {
                fullScreenLoader.stopLoader();
            }
        }

        /**
         * Render PayPal buttons based on configuration
         */
        async renderPayPalButtons(buttonsConfig) {
            try {
                this.clearButtonContainer();
                const buttons = this.buildButtonConfiguration(buttonsConfig);

                if (!this.paypalComponent || typeof this.paypalComponent.buttons !== 'function') {
                    console.warn(`[${this.code}] PayPal component not available for rendering buttons`);
                    return;
                }

                return await this.paypalComponent.buttons({
                    data: this.buildButtonData(buttonsConfig, buttons),
                    hooks: this.buildButtonHooks()
                });
            } catch (error) {
                console.error(`[${this.code}] Error rendering PayPal buttons:`, error);
            }
        }

        /**
         * Clear the button container
         */
        clearButtonContainer() {
            const container = document.querySelector('#' + this.buttonContainerId);
            if (container) {
                while (container.children.length > 0) {
                    container.removeChild(container.lastChild);
                }
            }
        }

        /**
         * Build button configuration based on payment method
         */
        buildButtonConfiguration(buttonsConfig) {
            const buttons = {};

            if (this.code === 'fiserv_paypal') {
                buttons.paypal = {
                    parentElementId: buttonsConfig.data?.buttons?.paypal?.parentElementId || this.buttonContainerId,
                    color: buttonsConfig.data?.buttons?.paypal?.color || DEFAULT_BUTTON_CONFIG.paypal.color,
                    shape: buttonsConfig.data?.buttons?.paypal?.shape || DEFAULT_BUTTON_CONFIG.paypal.shape,
                    label: buttonsConfig.data?.buttons?.paypal?.label || DEFAULT_BUTTON_CONFIG.paypal.label
                };
            } else if (this.code === 'fiserv_venmo') {
                buttons.venmo = {
                    parentElementId: buttonsConfig.data?.buttons?.venmo?.parentElementId || this.buttonContainerId,
                    color: buttonsConfig.data?.buttons?.venmo?.color || DEFAULT_BUTTON_CONFIG.venmo.color,
                    shape: buttonsConfig.data?.buttons?.venmo?.shape || DEFAULT_BUTTON_CONFIG.venmo.shape
                };
            }

            return buttons;
        }

        /**
         * Build button data for PayPal component
         */
        buildButtonData(buttonsConfig, buttons) {
            return {
                enableVaulting: buttonsConfig.data?.vaulting || false,
                customerConfirmation: buttonsConfig.data?.customerConfirmation ||
                    (this.code === 'fiserv_paypal' ? CUSTOMER_CONFIRMATION.REVIEW_AND_PAY : CUSTOMER_CONFIRMATION.PAY_NOW),
                buttons: buttons
            };
        }

        /**
         * Build button hooks for PayPal component
         */
        buildButtonHooks() {
            return {
                onApprove: async (data, actions) => {
                    console.log('onApprove called', data);
                    const email = this.extractCustomerEmail();
                    this.additionalData.paypal_order_id = data.orderId;
                    this.additionalData.email = email;
                    if (typeof this.placeOrder === 'function') {
                        this.placeOrder('parent');
                    }
                },
                onCancel: () => {
                    console.log('onCancel called');
                },
                onError: (error) => {
                    console.log('onError called', error);
                }
            };
        }

        /**
         * Extract customer email from various sources
         */
        extractCustomerEmail() {
            try {
                let email = '';

                if (window.checkoutConfig?.isCustomerLoggedIn && window.checkoutConfig?.customerData?.email) {
                    email = window.checkoutConfig.customerData.email;
                } else if (quote && quote.guestEmail) {
                    email = quote.guestEmail;
                }

                return this.validateEmail(email) ? email : '';
            } catch (error) {
                console.warn(`[${this.code}] Error extracting customer email:`, error);
                return '';
            }
        }

        /**
         * Validate email format
         */
        validateEmail(email) {
            return email && EMAIL_REGEX.test(email);
        }

        /**
         * Get shipping address information
         */
        getShipping() {
            let shippingAddress = quote.shippingAddress();
            if (!shippingAddress) {
                shippingAddress = checkoutData.getShippingAddressFromData() ;
            }
            if (!shippingAddress) {
                return undefined;
            }
            if (!shippingAddress.street || !shippingAddress.firstname || !shippingAddress.lastname) {
                console.warn('Shipping address is incomplete:', shippingAddress);
                return undefined;
            }
            let streetArr = Array.isArray(shippingAddress.street)
                ? shippingAddress.street
                : Object.values(shippingAddress.street);
            const mappedAddress = {
                street: streetArr[0] || undefined,
                city: shippingAddress.city || undefined,
                stateOrProvince: shippingAddress.region || shippingAddress.region_id || undefined,
                postalCode: shippingAddress.postcode || undefined,
                country: shippingAddress.country_id || shippingAddress.countryId || shippingAddress.country || undefined
            };
            return {
                firstName: shippingAddress.firstname || undefined,
                lastName: shippingAddress.lastname || undefined,
                address: mappedAddress
            };
        }
    };
});
