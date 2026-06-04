define([
    'jquery',
    'Magento_Payment/js/view/payment/cc-form',
    'Magento_Ui/js/model/messageList',
    'Magento_Checkout/js/model/full-screen-loader',
    'Fiserv_Payments/js/ch-adapter',
    'Fiserv_Payments/js/action/create-commercehub-applepay-session',
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
                type: 'applepay',
                publicKeyHash: null
            },
            isPlaceOrderActionAllowed: ko.observable(false),
		initializedAmount: 0.00,
		reloadOnRender: false
        },

    	initialize: async function () {
    		this._super();
            this.observeBillingAddress();
		
		    return this;
        },

    	initializeApplePay: async function () 
    	{
		// stop-gap until checkouts team fixes amount caching issue
		let fastlaneAmount = window.checkoutConfig?.payment?.fiserv_paypal_fastlane?.initializedAmount;
		if (fastlaneAmount)
		{
			let grandTotal = quote.totals()['grand_total'];
			if (fastlaneAmount != grandTotal)
			{
				window.checkoutConfig.payment.fiserv_paypal_fastlane.initializedAmount = undefined
				this.reloadOnRender = true;
			}
		}

            if (this.getCode() === this.isChecked())
            {
                await this.loadApplePayForm();
            }
            this.watchPaymentMethods();
    	},

        observeBillingAddress: function () {
            quote.billingAddress.subscribe((address) => {
                this.isPlaceOrderActionAllowed(address !== null);
            });
        },

	observeTotals: function()
	{
		quote.totals.subscribe( (totals) => {
			if (
				this.getCode() === this.isChecked() &&
				this.initializedAmount && 
				this.initializedAmount > 0.00 && 
				totals && 
				totals['grand_total'] && 
				totals['grand_total'] != this.initializedAmount) {
				location.reload();
			}
		});
	},
	    
    loadApplePayForm: async function () {
 		const config = window.checkoutConfig.payment[this.getCode()];
		try {
			fullScreenLoader.startLoader();
			$('#applepay-button-container').empty();
			let merchantName = config.storeName;

			// Build session params. For recurring payments, merge the
			// subscription fields; for one-time payments pass only merchantName.
			let sessionParams = { merchantName };
			if (config.recurringEnabled) {
				sessionParams = Object.assign(sessionParams, this.buildRecurringSessionParams(config));
			}

			const creds = await chSession(sessionParams);

			if (!creds?.ch_credentials) {
				throw new Error('No credentials returned from backend');
			}

			this.paymentPayload.sessionId = creds.ch_credentials.sessionId;
			await chAdapter.initSdk(config, creds.ch_credentials);
				
            const buttonStyle = config.applepayButtonStyle ?? 'black';
            const buttonType = config.applepayButtonType ?? 'buy';
			await window.fiserv.components.applePay({
				data: {
					button: {
						parentElementId: "applepay-button-container",
						color: buttonStyle,
						type: buttonType,
						locale: "en-US"
					}
				},
				hooks: {
					onApprove: (data) => {
						this.paymentPayload.publicKeyHash =
							data?.publicKeyHash || data?.details?.publicKeyHash || null;

						try {
							this.onApplePaySuccess(data.details, data);
							if (typeof data.completePayment === 'function') {
								data.completePayment('SUCCESS');
							}
						} catch (e) {
							if (typeof data.completePayment === 'function') {
								data.completePayment('FAILURE');
							}
							throw e;
						}
					},
					onCancel: (data) => {
						if (typeof data?.completePayment === 'function') {
							data.completePayment('FAILURE');
						}
					},
					onError: (data) => {
						if (typeof data?.completePayment === 'function') {
							data.completePayment('FAILURE');
						}
						globalMessageList.addErrorMessage({
							message: $t('Error during Apple Pay checkout')
						});
					}
				}
			});
			this.initializedAmount = quote.totals()['grand_total'];
			this.observeTotals();

		} catch (error) {
			globalMessageList.addErrorMessage({
				message: $t('Error during Apple Pay checkout')
			});
		} finally {
			fullScreenLoader.stopLoader();
		}
	},

        /**
         * Builds the recurring-payment portion of the session payload from the
         * merchant's admin configuration (exposed via window.checkoutConfig).
         * Passed as `params` to the standard chSession action.
         */
        buildRecurringSessionParams: function (config) {
            const tokenProvider = config.recurringTokenProvider || 'FISERV_SECURE_VAULT';

            const transactionDetails = { tokenProvider };
            if (tokenProvider === 'PROVIDER_TOKEN' && config.recurringTokenNotificationUrl) {
                transactionDetails.tokenNotificationUrl = config.recurringTokenNotificationUrl;
            }

            const subscriptionPaymentDetails = {
                subscriptionType: config.recurringSubscriptionType || 'RECURRING',
                managementUrl:    config.recurringManagementUrl    || ''
            };
            if (config.recurringBillingAgreement) {
                subscriptionPaymentDetails.billingAgreement = config.recurringBillingAgreement;
            }
            if (config.recurringFreeCancellationDate) {
                subscriptionPaymentDetails.freeCancellationDate = config.recurringFreeCancellationDate;
                if (config.recurringFreeCancellationTimezone) {
                    subscriptionPaymentDetails.freeCancellationTimeZone = config.recurringFreeCancellationTimezone;
                }
            }
            if (Array.isArray(config.recurringPaymentItems) && config.recurringPaymentItems.length > 0) {
                subscriptionPaymentDetails.paymentItems = config.recurringPaymentItems;
            }

            return {
                orderData: {
                    orderDescription: config.recurringOrderDescription || ''
                },
                transactionDetails,
                additionalDataCommon: {
                    subscriptionPaymentDetails
                }
            };
        },

        watchPaymentMethods: function () {
            $(`[name="payment[method]"]`).on("click", (event) => {
                if (event.currentTarget.id === this.getCode()) {
			if (this.reloadOnRender)
			{
				this.reloadOnRender = false;
				location.reload();
			} else
			{
				this.loadApplePayForm();
			}
                }
            });
        },

        onApplePaySuccess: function (details, data) {
            this.additionalData = {
                applepay_order_id: data?.orderId,
                payment_source: data?.paymentMethod || 'applepay',
                payment_session: this.paymentPayload.sessionId,
                public_key_hash: this.paymentPayload.publicKeyHash
            };

            if (this.placeOrderCallback) {
                this.placeOrderCallback({
                    orderId: data.orderId,
                    email: quote.guestEmail || window.checkoutConfig.customerData?.email || 'guest@example.com',
                    merchantId: window.checkoutConfig.payment[this.getCode()].merchantId,
                    terminalId: window.checkoutConfig.payment[this.getCode()].terminalId
                });
            }
        },

        placeOrderCallback: function (approvalData) {
            this.additionalData.applepay_order_id = approvalData.orderId;
            require(['Magento_Checkout/js/action/place-order'], (placeOrderAction) => {
                placeOrderAction(this.getData()).done(() => {
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
                    payment_session: this.additionalData.payment_session,
                    public_key_hash: this.additionalData.public_key_hash
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
