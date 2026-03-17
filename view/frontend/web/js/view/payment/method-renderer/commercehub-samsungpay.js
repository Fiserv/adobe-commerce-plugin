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

		initializeSamsungPay: async function () {
			if (this.getCode() === this.isChecked()) {
				await this.loadSamsungPayForm();
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
					this.initializedAmount > 0.00 &&
					totals &&
					totals.grand_total &&
					totals.grand_total !== this.initializedAmount
				) {
					location.reload();
				}
			});
		},

		loadSamsungPayForm: async function () {
			const config = this.getPaymentConfig();

			try {
				if (this.samsungPayInitialized && this.paymentPayload.sessionId) {
					return;
				}

				fullScreenLoader.startLoader();
				$('#samsungpay-button-container').empty();

				const creds = await chSession({merchantName: config.storeName});
				if (!creds?.ch_credentials) {
					throw new Error('Error creating payment session');
				}

				this.paymentPayload.sessionId = creds.ch_credentials.sessionId;
				await chAdapter.initSdk(config, creds.ch_credentials);

				await this.mountSamsungPayComponent(config);
				this.initializedAmount = this.getGrandTotal();
				this.observeTotals();
			} catch (error) {
				this.showError($t('Samsung Pay error: ') + (error?.message || error));
			} finally {
				fullScreenLoader.stopLoader();
			}
		},

		mountSamsungPayComponent: async function (config) {
			const buttonStyle = this.getButtonStyle(config);

			const samsungComponent = await window.fiserv.components.samsungPay({
				data: {
					button: {
						parentElementId: 'samsungpay-button-container',
						color: buttonStyle
					}
				},
				hooks: {
					onApprove: (data) => {
						this.onSamsungPaySuccess(null, data);
					},
					onCancel: function () {},
					onError: () => {
						this.showError($t('Error during Samsung Pay checkout'));
					}
				}
			});

			if (samsungComponent) {
				this.samsungPayComponent = samsungComponent;
				this.samsungPayInitialized = true;
				this.attachContainerClickGuards();
			}
		},

		attachContainerClickGuards: function () {
			const stopClick = function (event) {
				event.stopPropagation();
				event.stopImmediatePropagation();
				event.preventDefault();
				return false;
			};

			try {
				$('#samsungpay-button-container')
					.off('click.fiservStop')
					.on('click.fiservStop', stopClick);
				$('#samsungpay-button-container *').on('click.fiservStop', stopClick);
			} catch (error) {
				return;
			}
		},

		getPaymentConfig: function () {
			return window.checkoutConfig.payment[this.getCode()];
		},

		getButtonStyle: function (config) {
			return config.samsungPayButtonColor ?? config.samsungpayButtonColor ?? 'black';
		},

		getGrandTotal: function () {
			const totals = quote.totals();
			return totals?.grand_total || 0;
		},

		showError: function (message) {
			globalMessageList.addErrorMessage({
				message: message
			});
		},

		watchPaymentMethods: function () {
			$('[name="payment[method]"]').on('click', (event) => {
				this.samsungPayInitialized = false;
				if (event.currentTarget.id === this.getCode()) {
					this.loadSamsungPayForm();
				}
			});
		},

		onSamsungPaySuccess: function (details, data) {
			this.additionalData = {
				payment_source: data?.paymentMethod || 'samsungpay',
				payment_session: this.paymentPayload.sessionId
			};
			this.placeOrder('parent');
		},

		getData: function () {
			return {
				method: this.getCode(),
				additional_data: {
					payment_source: this.additionalData.payment_source,
					payment_session: this.additionalData.payment_session
				}
			};
		},

		getCode: function () {
			return this.code;
		},

		showPrivacyStatement: function () {
			const config = this.getPaymentConfig();
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
