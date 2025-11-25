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

        async initchAdapter() {
            var self = this;
            var config = (window.checkoutConfig && window.checkoutConfig.payment) ? window.checkoutConfig.payment[self.code] : {};
            fullScreenLoader.startLoader();
            try {
                if (!window.fiserv || typeof window.fiserv.components.paypal !== 'function') {
                    globalMessageList.addErrorMessage({ message: $t('Fiserv SDK is not loaded. Please check your network and configuration.') });
                    console.error('[' + self.code + '] Fiserv SDK not loaded or window.fiserv.components.paypal not available');
                    return;
                }

                chAdapter.initialize(config);
                var credsResponse, creds;

                credsResponse = await commercehubSession({});
                creds = credsResponse && credsResponse[chAdapter.credentialsKey];
                if (!creds) {
                    throw new Error('No credentials returned from ' + self.code + ' session');
                }

                self.paymentPayload.sessionId = creds[chAdapter.sessionIdKey];
                await chAdapter.initSdk(config, creds);
                var intent = 'authorize';
                if (config.payment_action && config.payment_action.toLowerCase() === 'authorize_capture') {
                    intent = 'capture';
                }
                const paypalComponentConfig = { intent: intent };
                const paypalComponent = await chAdapter.loadPayPalComponent(paypalComponentConfig);
                this.paypalComponent = paypalComponent;
                const buttonsConfig = config.buttonConfig;
                await this.renderPayPalButtons(buttonsConfig);
            } catch (error) {
                globalMessageList.addErrorMessage({ message: $t(self.code + ' credentials error: ') + (error.message || error) });
                console.error('[' + self.code + '] Error in initchAdapter:', error);
            } finally {
                fullScreenLoader.stopLoader();
            }
        }

        async renderPayPalButtons(buttonsConfig) {
            try {
                const container = document.querySelector('#' + this.buttonContainerId);
                if(container){
                    while (container.children.length > 0) {
                        container.removeChild(container.lastChild);
                    }
                }

                const config = (window.checkoutConfig && window.checkoutConfig.payment) ? window.checkoutConfig.payment[this.code] : {};
                const buttons = {};

                if (this.code === 'fiserv_paypal') {
                    buttons.paypal = {
                        parentElementId: buttonsConfig.data?.buttons?.paypal?.parentElementId || this.buttonContainerId,
                        color: buttonsConfig.data?.buttons?.paypal?.color || 'gold',
                        shape: buttonsConfig.data?.buttons?.paypal?.shape || 'rect',
                        label: buttonsConfig.data?.buttons?.paypal?.label || 'paypal'
                    };
                } else if (this.code === 'fiserv_venmo') {
                    buttons.venmo = {
                        parentElementId: buttonsConfig.data?.buttons?.venmo?.parentElementId || this.buttonContainerId,
                        color: buttonsConfig.data?.buttons?.venmo?.color || 'blue',
                        shape: buttonsConfig.data?.buttons?.venmo?.shape || 'rect',
                    };
                } else {
                    // default fallback for other payment codes if any
                }

                if (!this.paypalComponent || typeof this.paypalComponent.buttons !== 'function') {
                    return;
                }

                return await this.paypalComponent.buttons({
                    data: {
                        enableVaulting: buttonsConfig.data?.vaulting || false,
                        customerConfirmation: buttonsConfig.data?.customerConfirmation || (this.code === 'fiserv_paypal' ? 'REVIEW_AND_PAY' : 'PAY_NOW'),
                        buttons: buttons
                    },
                    hooks: {
                        onApprove: async (data, actions) => {
                            console.log('on Approve called', data);
                            var email = '';
                            try {
                                if (window.checkoutConfig?.isCustomerLoggedIn && window.checkoutConfig?.customerData?.email) {
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
                            if (typeof this.placeOrder === 'function') {
                                this.placeOrder('parent');
                            }
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
                console.error('[' + this.code + '] Error rendering PayPal buttons:', error);
            }
        }

        getshipping() {
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
