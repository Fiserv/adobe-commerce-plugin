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
            template: 'Fiserv_Payments/payment/commercehub/samsungpay-form',
            code: 'fiserv_samsung_pay',
            additionalData: {},
            paymentPayload: {
                sessionId: null,
                orderId: null,
                type: 'samsungpay',
                publicKeyHash: null
            },
            isPlaceOrderActionAllowed: ko.observable(false),
        initializedAmount: 0.00,
        samsungPayInitialized: false
        },

    	initialize: async function () {
    		this._super();
            this.observeBillingAddress();
		
		    return this;
        },

    	initializeSamsungPay: async function () 
    	{

            if (this.getCode() === this.isChecked())
            {
                await this.loadSamsungPayForm();
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
	    
    loadSamsungPayForm: async function () {
 		const config = window.checkoutConfig.payment[this.getCode()];
		try {
			// Check if already initialized - prevent unnecessary re-initialization
			if (this.samsungPayInitialized && this.paymentPayload.sessionId) {
				return;
			}
			
			fullScreenLoader.startLoader();
			$('#samsungpay-button-container').empty();
			let merchantName = window.checkoutConfig.payment[this.getCode()]["storeName"];
			const creds = await chSession({ "merchantName" : merchantName });

			if (!creds?.ch_credentials) {
				throw new Error('No credentials returned from backend');
			}

			this.paymentPayload.sessionId = creds.ch_credentials.sessionId;
			await chAdapter.initSdk(config, creds.ch_credentials);
				
            const buttonStyle = (config.samsungPayButtonColor ?? config.samsungpayButtonColor) || 'black';
            try {
                const _samsungComp = await window.fiserv.components.samsungPay({
                    data: {
                        button: {
                            parentElementId: "samsungpay-button-container",
                            color: buttonStyle,
                        }
                    },
                    hooks: {
					onApprove: (data) => {
						this.paymentPayload.publicKeyHash =
							data?.publicKeyHash || data?.details?.publicKeyHash || null;

						try {
							this.onSamsungPaySuccess(data.details, data);
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
                            message: $t('Samsung Pay error occurred.')
                        });
                    }
                }
                });
                // store component if returned
                if (_samsungComp) {
                    this.samsungPayComponent = _samsungComp;
                    this.samsungPayInitialized = true;
                    try {
                        $('#samsungpay-button-container').off('click.fiservStop').on('click.fiservStop', function (e) {
                            e.stopPropagation();
                            e.stopImmediatePropagation();
                            e.preventDefault();
                            return false;
                        });
                        $('#samsungpay-button-container *').on('click.fiservStop', function (e) {
                            e.stopPropagation();
                            e.stopImmediatePropagation();
                            e.preventDefault();
                            return false;
                        });
                    } catch (e) {
                        // ignore errors attaching handler
                    }
                }
            } catch (sdkErr) {
                try { console.error('[Samsung Pay] components.samsungPay error:', sdkErr); } catch (e) {}
                try { console.dir(sdkErr); } catch (e) {}
                try { console.log('SamsungPay error own property names:', Object.getOwnPropertyNames(sdkErr)); } catch (e) {}
                try { console.log('SamsungPay error prototype props:', Object.getOwnPropertyNames(Object.getPrototypeOf(sdkErr))); } catch (e) {}
                try { if (sdkErr && sdkErr.details) console.log('sdkErr.details:', sdkErr.details); } catch (e) {}
                try { if (sdkErr && sdkErr.cause) console.log('sdkErr.cause:', sdkErr.cause); } catch (e) {}
                try { if (sdkErr && sdkErr.stack) console.log('sdkErr.stack:', sdkErr.stack); } catch (e) {}
                globalMessageList.addErrorMessage({ message: $t('Samsung Pay error occurred: ') + (sdkErr.message || sdkErr) });
                throw sdkErr;
            }
			this.initializedAmount = quote.totals()['grand_total'];
			this.observeTotals();

		} catch (error) {
			globalMessageList.addErrorMessage({
				message: $t('Samsung Pay error: ') + error.message
			});
		} finally {
			fullScreenLoader.stopLoader();
		}
	},

        watchPaymentMethods: function () {
            const self = this;
            $(`[name="payment[method]"]`).on("click", (event) => {
                if (event.currentTarget.id === this.getCode()) {
                    this.samsungPayInitialized = false;
                    this.loadSamsungPayForm();
                } else {
                    this.samsungPayInitialized = false;
                }
            });
        },

        onSamsungPaySuccess: function (details, data) {
            this.additionalData = {
                samsungpay_order_id: data?.orderId,
                payment_source: data?.paymentMethod || 'samsungpay',
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
            this.additionalData.samsungpay_order_id = approvalData.orderId;
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
                    samsungpay_order_id: this.additionalData.samsungpay_order_id,
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
