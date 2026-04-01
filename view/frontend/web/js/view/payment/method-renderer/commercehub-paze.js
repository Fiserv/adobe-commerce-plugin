define([
    'jquery',
    'Magento_Payment/js/view/payment/cc-form',
    'Magento_Ui/js/model/messageList',
    'Magento_Checkout/js/model/full-screen-loader',
    'Fiserv_Payments/js/ch-adapter',
    'Fiserv_Payments/js/action/create-commercehub-enriched-session',
    'Magento_Checkout/js/model/quote',
    'ko',
    'mage/translate',
    'domReady!'
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
            template: 'Fiserv_Payments/payment/commercehub/paze-form',
            code: 'fiserv_paze',
            additionalData: {},
            paymentPayload: {
                sessionId: null,
                orderId: null,
                type: 'paze',
                pazePaymentToken: null
            },
            isPlaceOrderActionAllowed: ko.observable(false),
            initializedAmount: 0.00,
            reloadOnRender: false,
            pazeComponent: null,
            pazeInitialized: false
        },

        initialize: async function () {
            this._super();
            this.observeBillingAddress();
            return this;
        },

        initializePaze: async function () {
            // stop-gap until checkouts team fixes amount caching issue
            let fastlaneAmount = window.checkoutConfig?.payment?.fiserv_paypal_fastlane?.initializedAmount;
            if (fastlaneAmount) {
                let grandTotal = quote.totals()['grand_total'];
                if (fastlaneAmount != grandTotal) {
                    window.checkoutConfig.payment.fiserv_paypal_fastlane.initializedAmount = undefined;
                    this.reloadOnRender = true;
                }
            }

            if (this.getCode() === this.isChecked()) {
                if (this.reloadOnRender) {
                    this.reloadOnRender = false;
                    location.reload();
                } else {
                    await this.loadPazeForm();
                }
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

        /**
         * Get customer email from checkout data
         * @returns {String}
         */
        getCustomerEmail: function () {
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
            return email;
        },

        /**
         * Get amount object for Paze
         * @returns {Object}
         */
        getAmount: function () {
            let rawGrandTotal = quote.totals() ? quote.totals().grand_total : 0;
            let grandTotal = Math.round(rawGrandTotal * 100) / 100;
		    let grandTotalNum = Number(rawGrandTotal) || 0;
		    let grandTotalStr = grandTotalNum.toFixed(2);
            let currency = quote.totals() ? quote.totals().quote_currency_code : "USD";
            return {
                total: grandTotalStr,
                currency: currency
            };
        },

        /**
         * Get mapped shipping address for Paze ecom object
         * @returns {Object}
         */
        getPazeShippingAddress: function () {
            const shippingAddress = this.getShippingAddress();
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
            return {
                name: (shippingAddress.firstname || '') + ' ' + (shippingAddress.lastname || ''),
                street: streetArr[0] || undefined,
                houseNumberOrName: streetArr[1] || undefined,
                recipientNameOrAddress: streetArr[2] || undefined,
                city: shippingAddress.city || undefined,
                stateOrProvince: shippingAddress.region || shippingAddress.region_id || undefined,
                postalCode: shippingAddress.postcode || undefined,
                country: shippingAddress.country_id || shippingAddress.countryId || shippingAddress.country || undefined
            };
        },

        /**
         * Build Paze selectPaymentMethod parameters (Step 5 of Paze documentation)
         * @returns {Object}
         */
        buildPazeSelectParams: function () {
            const amount = this.getAmount();

            return {
                amount: amount,
            };
        },

        loadPazeForm: async function () {
            const config = window.checkoutConfig.payment[this.getCode()];
            try {
                fullScreenLoader.startLoader();
                $('#paze-button-container').empty();

                // Initialize Paze component (Step 4 of documentation)
                const displayName = config.displayName || 'My Site';
                const cspNonce = config.cspNonce || crypto.randomUUID();
                const pazeComponentConfig = {
                    displayName: displayName,
                    cspNonce: cspNonce
                };

                // Get credentials from session
                const creds = await chSession({});

                if (!creds?.ch_credentials) {
                    throw new Error('No credentials returned from backend');
                }

                this.paymentPayload.sessionId = creds.ch_credentials.sessionId;
                this.paymentPayload.accessToken = creds.ch_credentials.accessToken;
                await chAdapter.initSdk(config, creds.ch_credentials);

                this.pazeComponent = await chAdapter.loadPazeComponent(pazeComponentConfig);
                this.pazeInitialized = true;

                // Create Paze payment button
                await this.renderPazeButton();
                this.initializedAmount = quote.totals()['grand_total'];
                this.observeTotals();

            } catch (error) {
                globalMessageList.addErrorMessage({
                    message: $t('Paze error: ') + error.message
                });
                console.error('[Paze] Error loading Paze form:', error);
            } finally {
                fullScreenLoader.stopLoader();
            }
        },

        /**
         * Renders Paze payment button
         * @returns {Promise}
         */
        renderPazeButton: async function () {
            try {
                const container = document.querySelector('#paze-button-container');
                if (container) {
                    while (container.children.length > 0) {
                        container.removeChild(container.lastChild);
                    }
                }

		// Create Paze payment button
		const pazeButton = document.createElement('button');
		pazeButton.type = 'button';

                pazeButton.className = 'action paze-pay-button';
                pazeButton.id = 'paze-pay-button';

		pazeButton.textContent = $t('Pay with Paze');
		pazeButton.style.cssText = 'background-color: #0066b2; color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; width: 100%;';

                pazeButton.addEventListener('click', async () => {
                    try {
                        await this.selectPazePaymentMethod();
                    } catch (error) {
                        console.error('[Paze] Button click error:', error);
                    }
                });

                if (container) {
                    container.appendChild(pazeButton);
                }

            } catch (error) {
                console.error('[Paze] Error rendering Paze button:', error);
            }
        },

        /**
         * Select Paze payment method - launches Paze UI (Step 5) and submits (Step 6)
         * @returns {Promise}
         */
        selectPazePaymentMethod: async function () {
            fullScreenLoader.startLoader();

            try {
                if (!this.pazeComponent || typeof this.pazeComponent.selectPaymentMethod !== 'function') {
                    throw new Error('Paze component not initialized or selectPaymentMethod not available');
                }

                // Step 5: Launch Paze UI and get user selection
                // Returns: { customer: {...}, card: {...} }
                const params = this.buildPazeSelectParams();

                const result = await this.pazeComponent.selectPaymentMethod(params);

                if (!result) {
                    throw new Error('No result returned from selectPaymentMethod');
                }

                // Step 6: Submit the Paze payment method
                // Params are optional if amount hasn't changed since selectPaymentMethod
                const submitResult = await this.pazeComponent.submit(params);

                // Store the card data and submit result for backend processing
                // Note: Paze never returns orderId - that's generated by backend Charges API
                this.paymentPayload.pazePaymentToken = JSON.stringify({
                    submitResult: submitResult,
                    card: result.card,
                    customer: result.customer
                });
                this.onPazeSuccess(result, submitResult);

            } catch (error) {
                console.error('[Paze] Error in Paze payment flow:', error);
                globalMessageList.addErrorMessage({ message: $t('Paze payment error: ') + (error.message || error) });
            } finally {
                fullScreenLoader.stopLoader();
            }
        },

        /**
         * Handle Paze payment success
         * @param {Object} selectResult - Result from selectPaymentMethod with customer/card data
         * @param {Object} submitResult - Result from submit() call (card capture API response)
         */
        onPazeSuccess: function (selectResult, submitResult) {
            // Paze returns customer and card data, but never returns orderId
            // The orderId is generated by backend Charges API using the sessionId
            const customerEmail = selectResult?.customer?.email || this.getCustomerEmail() || quote.guestEmail;
            const cardLast4 = selectResult?.card?.last4 || 'unknown';
            
            this.additionalData = {
                payment_source: 'paze',
                payment_session: this.paymentPayload.sessionId,
                paze_payment_token: this.paymentPayload.pazePaymentToken,
                customer_email: customerEmail,
                card_last4: cardLast4
            };

	    // Use inherited placeOrder method to trigger Magento's default flow
	    this.placeOrder('parent');
        },

        /**
         * Get payment data for backend processing
         * Backend will use sessionId with Charges API (sourceType: PaymentSession)
         * @returns {Object}
         */
        getData: function () {
            return {
                method: this.getCode(),
                additional_data: {
                    payment_source: this.additionalData.payment_source,
                    payment_session: this.additionalData.payment_session,
                    paze_payment_token: this.additionalData.paze_payment_token,
                    customer_email: this.additionalData.customer_email,
                    card_last4: this.additionalData.card_last4
                }
            };
        },

        watchPaymentMethods: function () {
            $('[name="payment[method]"]').on("click", (event) => {
                if (event.currentTarget.id === this.getCode()) {
                    if (this.reloadOnRender) {
                        this.reloadOnRender = false;
                        location.reload();
                    } else {
                        this.loadPazeForm();
                    }
                }
            });
        },

        getShippingAddress: function () {
            let shippingAddress = quote.shippingAddress();
            if (!shippingAddress) {
                const checkoutData = require('Magento_Checkout/js/checkout-data');
                shippingAddress = checkoutData.getShippingAddressFromData();
            }
            return shippingAddress;
        },

        getCode: function () {
            return this.code;
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

