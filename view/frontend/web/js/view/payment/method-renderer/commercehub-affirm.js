define([
    'jquery',
    'Magento_Payment/js/view/payment/cc-form',
    'Magento_Ui/js/model/messageList',
    'Magento_Checkout/js/model/full-screen-loader',
    'Fiserv_Payments/js/ch-adapter',
    'Fiserv_Payments/js/action/create-commercehub-affirm-session',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/checkout-data',
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
    checkoutData,
    ko,
    $t
) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Fiserv_Payments/payment/commercehub/affirm-form',
            code: 'fiserv_affirm',
            additionalData: {},
            paymentPayload: {
                sessionId: null,
                orderId: null,
                type: 'affirm',
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

        afterRenderInit: function () {
            // Check if Affirm was previously initialized and if amount changed (needs reload)
            let config = window.checkoutConfig?.payment?.[this.getCode()];
            if (config) {
                let storedInitializedAmount = config.initializedAmount;
                if (storedInitializedAmount) {
                    let grandTotal = quote.totals()?.['grand_total'];
                    if (grandTotal && storedInitializedAmount != grandTotal) {
                        // Amount changed, need to reload to reinitialize Affirm with new amount
                        config.initializedAmount = undefined;
                        this.reloadOnRender = true;
                    }
                }
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
	    
    initializeAffirm: async function () {
 		const config = window.checkoutConfig.payment[this.getCode()];
		try {
			fullScreenLoader.startLoader();
			$('#affirm-button-container').empty();
			let merchantName = window.checkoutConfig.payment[this.getCode()]["storeName"];
			const creds = await chSession({ "merchantName" : merchantName });

			if (!creds?.ch_credentials) {
				throw new Error('No credentials returned from backend');
			}

			this.paymentPayload.sessionId = creds.ch_credentials.sessionId;
			await chAdapter.initSdk(config, creds.ch_credentials);
				
            const buttonStyle = config.affirmButtonColor ?? config.affirmButtonStyle ?? 'default';
            const backendAction = config.paymentAction || config.affirmPaymentAction || config.payment_action || '';
                let affirmIntent = 'AUTHORIZE';
                const actionLower = (backendAction || '').toString().toLowerCase();
                if (actionLower.includes('capture') || actionLower.includes('sale')) {
                    affirmIntent = 'CAPTURE';
                } else if (actionLower.includes('authorize') || actionLower === 'auth' || actionLower.includes('auth')) {
                    affirmIntent = 'AUTHORIZE';
            }

            const affirmPayload = {
                data: {
                    intent: affirmIntent,
                    button: {
                        parentElementId: "affirm-button-container",
                        color: buttonStyle
                    },
                },
				hooks: {
					onApprove: async (data, actions) => {
                            console.log('Affirm onApprove called', data);
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
                            // For Affirm, use data.id instead of data.orderId
                            this.additionalData.paypal_order_id = data.id || data.orderId;
                            this.additionalData.email = email;
                            console.log('Affirm additionalData:', this.additionalData);
                            // Use inherited placeOrder method to trigger Magento's default flow
                            this.placeOrder('parent');
                        },
                        onError: error => {
                            console.log('Affirm onError called', error);
                        }
				}
			};
            await window.fiserv.components.affirm(affirmPayload);
			let grandTotal = quote.totals()['grand_total'];
			this.initializedAmount = grandTotal;
			let affirmConfig = window.checkoutConfig.payment[this.getCode()];
			if (affirmConfig) {
				affirmConfig.initializedAmount = grandTotal;
			}
			this.observeTotals();

		} catch (error) {
			console.error('Affirm initialization error:', error);
			globalMessageList.addErrorMessage({
				message: $t('Affirm error: ') + error.message
			});
		} finally {
			fullScreenLoader.stopLoader();
		}
	},

        loadAffirmForm: function () {
            try {
                if (this.getCode() === this.isChecked()) {
                    // initializeAffirm is async - call and ignore returned promise
                    this.initializeAffirm();
                }
            } catch (error) {
                globalMessageList.addErrorMessage({
                    message: $t('Affirm initialization failed: ') + (error && error.message ? error.message : error)
                });
            }
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
				this.loadAffirmForm();
			}
                }
            });
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
