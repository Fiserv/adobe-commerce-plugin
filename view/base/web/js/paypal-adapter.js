/*browser:true*/
/*global define*/
define([
    'jquery',
    'SDCv2Library',
    'Magento_Checkout/js/model/quote'
], function (
    $,
    sdcv2,
    quote
) {
    'use strict';

    return {
        credentialsErrorMsg: "Oops! Something went wrong...",
        credentialsUrl: "/fiserv/paypal/getcredentials",
        clientScriptId: "paypal",
        configApiKeyKey: "apiKey",
        configMerchantIdKey: "merchantId",
        configTerminalIdKey: "terminalId",
        configEncryptionAlgoKey: "asymmetricEncryptionAlgorithm",
        environmentKey: "environment",
        credentialsKey: "paypal_credentials",
        keyIdKey: "keyId",
        accessTokenKey: "accessToken",
        publicTokenKey: "publicKey",
        sessionIdKey: "sessionId",
        customerIdKey: "customerId",
        encryptionAlgoKey: "symmetricEncryptionAlgorithm",
        prodEnv: "PROD",
        certEnv: "CERT",
        config: {},
    paypalComponent: undefined,
    sdkInitialized: false,

        /**
         * Initialize the SDK
         * @param {Object} config - SDK configuration
         */
        initialize: async function (config) {
            this.config = config;
        },

        async initSdk(config, creds) {
            if (this.sdkInitialized) {
                return;
            }
            await window.fiserv.init({
                cspNonce: crypto.randomUUID(),
                environment: config.environment,
                accessToken: creds[this.accessTokenKey],
                apiKey: config.apiKey,
                merchantId: config.merchantId,
                publicKey: creds[this.publicTokenKey],
                keyId: creds[this.keyIdKey],
                terminalId: config[this.configTerminalIdKey],
                sessionId: creds[this.sessionIdKey]
            });
            this.sdkInitialized = true;
        },

        /**
         * Load PayPal SDK
         * @param {Object} options - { customerId, intent }
         * @returns {Promise<Object>} PayPal component instance
         */
        async loadPayPalComponent(options) {
            if (!window.fiserv || typeof window.fiserv.components.paypal !== 'function') {
                throw new Error('Fiserv SDK not loaded or window.fiserv.components.paypal not available');
            }
            this.paypalComponent = await window.fiserv.components.paypal(options);
            return this.paypalComponent;
        },

        getPayPalCredentials(storeUrl, successCb, errorCb) {
            const validateCb = this.validateCredentialsResponse.bind(this);
            const parseResponseCb = this.parseCredentialsResponse.bind(this);
            const errorMsg = this.credentialsErrorMsg;

            $.ajax({
                url: storeUrl + this.credentialsUrl,
                cache: false,
                dataType: 'json',
                type: "POST",
                data: JSON.stringify({}),
                contentType: "application/json",
                success(response) {
                    if (!validateCb(response)) {
                        errorCb(errorMsg);
                        return;
                    }
                    successCb(parseResponseCb(response));
                },
                error(err) {
                    errorCb(errorMsg);
                    console.error('PayPal credentials AJAX error:', err);
                }
            });
            console.error('PayPal credentials requested');
        },

        validateCredentialsResponse: function (response) {
			let credArray = response[this.credentialsKey];
			if (credArray === undefined) {
                console.error('PayPal credentials not found in response');
				return false;
			}
			if (credArray[this.accessTokenKey] === undefined) {
                console.error('PayPal access token not found in response');
				return false;
			}
			if (credArray[this.publicTokenKey] === undefined) {
				console.error('PayPal public token not found in response');
				return false;
			}
			if (credArray[this.sessionIdKey] === undefined) {
				console.error('PayPal session ID not found in response');
				return false;
			}
			if (credArray[this.encryptionAlgoKey] === undefined) {
				console.error('PayPal encryption algorithm not found in response');
				return false;
			}

			return true;
		},


		parseChCredentialsResponse: function (response) {
			return response[this.credentialsKey];
		},

        /**
         * Renders PayPal and Venmo buttons
         * @param {Object} buttonsConfig - ButtonsConfig object (see Fiserv docs)
         * @returns {Promise}
         */
        async renderPayPalButtons(buttonsConfig) {
            var rendererInstance = null;
            try {
                rendererInstance = require('Fiserv_Payments/js/view/payment/method-renderer/commercehub-paypal');
            } catch (e) {
                // If not found, fallback to null
                rendererInstance = null;
                console.error('Renderer instance not found:', e);
            }
            const containerId ='#paypal-button-container';
            // const venmoContainerId = '#venmo-button-container';
            const container = document.querySelector(containerId);
            // const venmoContainer = document.querySelector(venmoContainerId);
            if(container){
                while (container.children.length > 0) {
                    container.removeChild(container.lastChild);
                    console.log('Removed child from PayPal button container');
                }
            }
            // if(venmoContainer){
            //     while (venmoContainer.children.length > 0) {
            //         venmoContainer.removeChild(venmoContainer.lastChild);
            //         console.log('Removed child from Venmo button container');
            //     }
            // }
            
            const config = this.config || {};
            const buttons = {
                paypal: {
                    parentElementId: buttonsConfig.data && buttonsConfig.data.buttons && buttonsConfig.data.buttons.paypal.parentElementId || 'paypal-button-container',
                    color: buttonsConfig.data && buttonsConfig.data.buttons && buttonsConfig.data.buttons.paypal.color || 'gold',
                    shape: buttonsConfig.data && buttonsConfig.data.buttons && buttonsConfig.data.buttons.paypal.shape || 'rect',
                    label: buttonsConfig.data && buttonsConfig.data.buttons && buttonsConfig.data.buttons.paypal.label || 'paypal'
                }
            };
            // if (config.venmoConfig && config.venmoConfig.enableVenmo) {
            //     buttons.venmo = {
            //         parentElementId: buttonsConfig.data && buttonsConfig.data.buttons && buttonsConfig.data.buttons.venmo.parentElementId || 'venmo-button-container',
            //         color: buttonsConfig.data && buttonsConfig.data.buttons && buttonsConfig.data.buttons.venmo.color || 'gold',
            //         shape: buttonsConfig.data && buttonsConfig.data.buttons && buttonsConfig.data.buttons.venmo.shape || 'rect'
            //     };
            // }
            return this.paypalComponent.buttons({
                data: {
                    enableVaulting: buttonsConfig.data && buttonsConfig.data.enableVaulting !== undefined ? buttonsConfig.data.enableVaulting : false,
                    customerConfirmation: buttonsConfig.data && typeof buttonsConfig.data.customerConfirmation === 'string' ? buttonsConfig.data.customerConfirmation : 'PAY_NOW',
                    buttons: buttons
                },
                hooks:{
                        onApprove: async (data, actions) => {
                            console.log("onApprove data", data);
                            var email = '';
                            try {
                                if (window.checkoutConfig && window.checkoutConfig.isCustomerLoggedIn && window.checkoutConfig.customerData && window.checkoutConfig.customerData.email) {
                                    email = window.checkoutConfig.customerData.email;
                                }
                                else if (quote && quote.guestEmail) {
                                    email = quote.guestEmail;
                                }
                                if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                                    console.error('[PayPal] Invalid or empty email address:', email);
                                    email = '';
                                } else {
                                    console.log('[PayPal] Valid email address confirmed:', email);
                                }
                            } catch (error) {
                                console.error('[PayPal] Error retrieving email address:', error);
                                email = '';
                            }
                            return fetch('/fiserv/paypal/validate', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({
                                    orderId: data.orderId,
                                    email: email,
                                    merchantId: config.merchantId,
                                    terminalId: config.terminalId
                                })
                            })
                        .then(res => {
                            // Defensive: check if response is JSON
                            const contentType = res.headers.get('content-type');
                            if (contentType && contentType.indexOf('application/json') !== -1) {
                                return res.json();
                            } else {
                                throw new Error('Invalid response from server (not JSON)');
                            }
                        })
                        .then(res => {
                            if (res.success) {
                                // Clear Magento cart after successful payment
                                require(['Magento_Customer/js/customer-data'], function (customerData) {
                                        customerData.invalidate(['cart']);
                                        customerData.reload(['cart'], true);
                                    });
                                     window.location.href = '/checkout/onepage/success/';
                            } else {
                                // throw new Error(res.message || 'Payment validation failed');
                                console.error('Payment validation failed:', res.message);
                            }
                        });
                    },
                    onCancel: () => {
                        console.log("on cancel called");
                    },
                    onError: error => {
                        console.log("on error called", error);
                    }
                }
            });
        },
    };
});
