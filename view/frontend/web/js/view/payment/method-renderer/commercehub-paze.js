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
            pazeInitialized: false,
            totalsSubscription: null,
            paymentMethodsWatcherAttached: false
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
            if (this.totalsSubscription) {
                return;
            }

            this.totalsSubscription = quote.totals.subscribe((totals) => {
                if (!totals || typeof totals.grand_total === 'undefined') {
                    return;
                }

                const currentTotal = Number(totals.grand_total);
                const initialTotal = Number(this.initializedAmount);

                if (
                    this.getCode() === this.isChecked() &&
                    initialTotal > 0 &&
                    Number.isFinite(currentTotal) &&
                    Number.isFinite(initialTotal) &&
                    currentTotal !== initialTotal
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

        loadPazeForm: async function () {
            const config = window.checkoutConfig.payment[this.getCode()];
            try {
                fullScreenLoader.startLoader();
                $('#paze-button-container').empty();

                const [creds, transactionData] = await Promise.all([
                    chSession({}),
                    this.getOrderData()
                ]);

                if (!creds?.ch_credentials) {
                    throw new Error($t('No credentials returned from backend'));
                }

                this.paymentPayload.sessionId = creds.ch_credentials.sessionId;
                this.paymentPayload.accessToken = creds.ch_credentials.accessToken;

                // Keep Adobe flow, but initialize SDK before rendering Paze button.
                await chAdapter.initSdk(config, creds.ch_credentials);

                // Enforce context-based render path only.
                if (!this.renderPazeContext(config, transactionData)) {
                    throw new Error('Unable to render Paze with SDK context path');
                }

                this.initializedAmount = quote.totals()['grand_total'];
                this.observeTotals();

            } catch (error) {
                this.sdkLoadFailure(error);
            } finally {
                // Do not keep checkout blocked while waiting for async Paze events.
                fullScreenLoader.stopLoader();
            }
        },

        renderPazeContext: function (config, transactionData) {
            const container = document.getElementById('paze-button-container');
            if (!container) {
                return false;
            }

            if (!window.fiserv || typeof window.fiserv.context !== 'function') {
                return false;
            }

            const context = window.fiserv.context();
            if (!context) {
                return false;
            }

            if (!context.config) {
                context.config = {};
            }

            // Inject order amount onto the active SDK context before rendering the button.
            context.config.amount = transactionData.amount;

            const pazeButtonElement = this.createPazeButtonElement(config, context, transactionData);
            if (!pazeButtonElement) {
                return false;
            }

            container.replaceChildren(pazeButtonElement);
            container.setAttribute('data-paze-render-mode', 'context');
            window.__pazeRenderMode = 'context';
            this.pazeInitialized = true;

            return true;
        },

        createPazeButtonElement: function (config, context, transactionData) {
            const pazeButtonElement = document.createElement('fiserv-paze-button');
            pazeButtonElement.displayName = config.displayName || config.storeName || 'My Site';
            pazeButtonElement.context = context;
            pazeButtonElement.transactionData = transactionData;

            pazeButtonElement.addEventListener('fiserv-paze-success', (event) => {
                fullScreenLoader.startLoader();
                try {
                    this.handlePazeButtonSuccess(event);
                } catch (error) {
                    this.handlePazeError(error);
                } finally {
                    fullScreenLoader.stopLoader();
                }
            });

            pazeButtonElement.addEventListener('fiserv-paze-error', (event) => {
                const detail = event?.detail || {};
                if (detail?.description === 'Paze operation cancelled') {
                    this.handlePazeCancel();
                    return;
                }

                this.handlePazeError(detail);
            });

            return pazeButtonElement;
        },

        handlePazeButtonSuccess: function (event) {
            const detail = event?.detail || {};
            const selectResult = {
                card: detail.card,
                customer: detail.customer
            };
            const submitResult = detail.submitResult || detail;

            this.paymentPayload.pazePaymentToken = JSON.stringify({
                submitResult: submitResult,
                card: selectResult.card,
                customer: selectResult.customer
            });

            this.onPazeSuccess(selectResult, submitResult);
        },

        getOrderData: async function () {
            const amount = this.getAmount();

            return {
                amount: {
                    currency: amount.currency,
                    total: amount.total
                }
            };
        },

        handlePazeCancel: function () {
            console.log('[Paze] Payment cancelled by customer.');
        },

        handlePazeError: function (error) {
            const message = error?.message || error?.description || error;
            globalMessageList.addErrorMessage({
                message: $t('Paze payment error: ') + message
            });
        },

        sdkLoadFailure: function (error) {
            console.error('[Paze] Error loading Paze form:', error);
            window.__pazeRenderMode = 'context-failed';
            globalMessageList.addErrorMessage({
                message: $t('Paze error: ') + (error?.message || error)
            });
            fullScreenLoader.stopLoader();
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
            if (this.paymentMethodsWatcherAttached) {
                return;
            }

            this.paymentMethodsWatcherAttached = true;
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
