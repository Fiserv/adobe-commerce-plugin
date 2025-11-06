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
		initializedAmount: 0.00
        },

	initialize: async function () {
		this._super();

            	this.observeBillingAddress();
		
		return this;
        },

	initializeApplePay: async function () 
	{
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
			let merchantName = window.checkoutConfig.payment[this.getCode()]["storeName"];
			const creds = await chSession({ "merchantName" : merchantName });

			if (!creds?.ch_credentials) {
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
							message: $t('Apple Pay error occurred.')
						});
					}
				}
			});
			this.initializedAmount = quote.totals()['grand_total'];
			this.observeTotals();

		} catch (error) {
			globalMessageList.addErrorMessage({
				message: $t('Apple Pay error: ') + error.message
			});
		} finally {
			fullScreenLoader.stopLoader();
		}
	},

        watchPaymentMethods: function () {
            const self = this;
            $(`[name="payment[method]"]`).on("click", function () {
                if ($(this).attr("id") === self.getCode()) {
                    self.loadApplePayForm();
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
