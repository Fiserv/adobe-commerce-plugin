/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define([
    'jquery',
    'Magento_Payment/js/view/payment/cc-form',
    'Fiserv_Payments/js/ch-adapter',
    'Fiserv_Payments/js/action/create-commercehub-enriched-session',
    'Magento_Checkout/js/model/quote',
    'Magento_Ui/js/model/messageList',
    'Magento_Checkout/js/checkout-data',
    'Magento_Checkout/js/model/full-screen-loader',
    'ko',
    'mage/translate',
    'domReady!'
], function (
    $,
    Component,
    chAdapter,
    commercehubSession,
    quote,
    globalMessageList,
    checkoutData,
    fullScreenLoader,
    ko,
    $t
) {
    'use strict';

    return Component.extend({
        isPlaceOrderActionAllowed: ko.observable(false),
        defaults: {
            template: 'Fiserv_Payments/payment/commercehub/venmo-form',
            code: 'fiserv_venmo',
            active: false,
            sortOrder: 20,
            paymentPayload: {
                sessionId: null,
                type: null,
                orderID: null
            },
            additionalData: {},
            paymentMethodName: '[name="payment[method]"',
            credentials: undefined,
            paypalComponent: null,
            vaultEnabler: null
        },

        initialize: function () {
            quote.billingAddress.subscribe(function (address) {
                this.isPlaceOrderActionAllowed(address !== null);
            }, this);

            this._super();
            this.loadPayPalForm();
			return this;
        },

       loadPayPalForm: function () {
            if (this.isChecked() === this.getCode()) {
                this.initchAdapter();
            } 
        },

        initchAdapter: async function () {
            var self = this;
            var config = (window.checkoutConfig && window.checkoutConfig.payment) ? window.checkoutConfig.payment[self.getCode()] : {};
            fullScreenLoader.startLoader();
            try {
                if (!window.fiserv || typeof window.fiserv.components.paypal !== 'function') {
                    globalMessageList.addErrorMessage({ message: $t('Fiserv SDK is not loaded. Please check your network and configuration.') });
                    console.error('[PayPal] Fiserv SDK not loaded or window.fiserv.components.paypal not available');
                    return;
                }

                chAdapter.initialize(config);
                var credsResponse, creds;

                credsResponse = await commercehubSession({});
                creds = credsResponse && credsResponse[chAdapter.credentialsKey];
                if (!creds) {
                    throw new Error('No credentials returned from Venmo session');
                }

                self.paymentPayload.sessionId = creds[chAdapter.sessionIdKey];
                await chAdapter.initSdk(config, creds);
                var intent = 'authorize';
                if (config.payment_action.toLowerCase() === 'authorize_capture') {
                    intent = 'capture';
                }
                const shippingAddress = this.getMapShippingAddress(this.getShippingAddress());
                var shippingPreference = 'SET_PROVIDED_ADDRESS';
                const paypalComponentConfig = {
                    intent: intent,
                    // shippingAddress: shippingAddress,
                    // shippingPreference: shippingPreference,
                };
                const paypalComponent = await chAdapter.loadPayPalComponent(paypalComponentConfig);
                this.paypalComponent = paypalComponent;
                const buttonsConfig = config.buttonConfig;
                await this.renderPayPalButtons(buttonsConfig);
            } catch (error) {
                globalMessageList.addErrorMessage({ message: $t('Venmo credentials error: ') + (error.message || error) });
                console.error('[Venmo] Error in initchAdapter:', error);
            } finally {
                fullScreenLoader.stopLoader();
            }
        },

        /**
         * Renders Venmo buttons
         * @param {Object} buttonsConfig - ButtonsConfig object (see Fiserv docs)
         * @returns {Promise}
         */
        async renderPayPalButtons(buttonsConfig) {
            try {
                const containerId ='#venmo-button-container';
                const container = document.querySelector(containerId);
                if(container){
                    while (container.children.length > 0) {
                        container.removeChild(container.lastChild);
                    }
                }

                const config = (window.checkoutConfig && window.checkoutConfig.payment) ? window.checkoutConfig.payment[this.getCode()] : {};
                const buttons = {
                    venmo: {
                        parentElementId: buttonsConfig.data && buttonsConfig.data.buttons && buttonsConfig.data.buttons.venmo.parentElementId || 'venmo-button-container',
                        shape: buttonsConfig.data && buttonsConfig.data.buttons && buttonsConfig.data.buttons.venmo.shape || 'rect',
                    }
                };

                if (!this.paypalComponent || typeof this.paypalComponent.buttons !== 'function') {
                    return;
                }

                return await this.paypalComponent.buttons({
                    data: {
                        customerConfirmation: buttonsConfig.data && typeof buttonsConfig.data.customerConfirmation === 'string' ? buttonsConfig.data.customerConfirmation : 'PAY_NOW',
                        buttons: buttons
                    },
                    hooks: {
                        onApprove: async (data, actions) => {
                            console.log('on Approve called', data);
                            var email = '';
                            try {
                                if (window.checkoutConfig && window.checkoutConfig.isCustomerLoggedIn && window.checkoutConfig.customerData && window.checkoutConfig.customerData.email) {
                                    email = window.checkoutConfig.customerData.email;
                                } else if (quote && quote.guestEmail) {
                                    email = quote.guestEmail;
                                }
                                if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                                    email = '';
                                }
                            } catch (error) {
                                email = '';
                            }
                            this.additionalData.paypal_order_id = data.orderId;
                            this.additionalData.email = email;
                            // Use inherited placeOrder method to trigger Magento's default flow
                            this.placeOrder('parent');
                        },
                        onCancel: () => {
                            console.log('on cancel called');
                        },
                        onError: error => {
                            console.log('on error called', error);
                        }
                    }
                });
            } catch (error) {
                console.error('[Venmo] Error rendering PayPal buttons:', error);
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

        isVenmoEnabled: function () {
            var config = (window.checkoutConfig && window.checkoutConfig.payment) ? window.checkoutConfig.payment[this.getCode()] : {};
            return config && config.venmoConfig && config.venmoConfig.enableVenmo;
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

        /**
         * Show error message
         *
         * @param {String} errorMessage
         * @private
         */
        showError: function (errorMessage) {
            globalMessageList.addErrorMessage({
                message: errorMessage
            });
        },

        getShippingAddress: function () {
            let shippingAddress = quote.shippingAddress();
            if (!shippingAddress) {
                shippingAddress = checkoutData.getShippingAddressFromData() ;
            }
            return shippingAddress;
        },

        getMapShippingAddress: function (address) {
            if (!address) {
                return undefined;
            }
            if (!address.street || !address.firstname || !address.lastname) {
                console.warn('Shipping address is incomplete:', address);
                return undefined;
            }
            let streetArr = Array.isArray(address.street)
                ? address.street
                : Object.values(address.street);
            const mappedAddress = {
                street: streetArr[0] || undefined,
                city: address.city || undefined,
                stateOrProvince: address.region || address.region_id || undefined,
                postalCode: address.postcode || undefined,
                country: address.country_id || address.countryId || address.country || undefined
            };
            return {
                firstName: address.firstname || undefined,
                lastName: address.lastname || undefined,
                address: mappedAddress
            };
        },

        /**
		 * Set list of observable attributes
		 *
		 * @returns {exports.initObservable}
		 */
		initObservable: function () {
			this._super()
				.observe(['active']);
			return this;
		},

        watchPaymentMethods: function(element) {
            let self = this;
			$(self.paymentMethodName).on("click", function() {
                let selected = $(this).attr("id");
				if (selected === self.getCode()) {
                    self.loadPayPalForm();
                } else {
                    $('#venmo-button-container').empty();
                }
            });
        },

        refreshBillingAddress: function () {
			if (this.isAchActive() && quote.billingAddress()) {
				shpfUtils.setBillingAddress(quote.billingAddress());
				this.initAchIframe();
			}
		}
    });
});
