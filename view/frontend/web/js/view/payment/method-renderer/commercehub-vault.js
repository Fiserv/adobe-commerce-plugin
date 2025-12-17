define([
	'jquery',
	'underscore',
	'Magento_Vault/js/view/payment/method-renderer/vault',
	'Magento_Ui/js/model/messageList',
	'Magento_Checkout/js/model/full-screen-loader',
	'Fiserv_Payments/js/ch-adapter',
	'Fiserv_Payments/js/action/create-commercehub-enriched-session',
	'ko'
], function(
	$,
	_,
	VaultComponent,
	globalMessageList,
	fullScreenLoader,
	chAdapter,
	chSession,
	ko
){
	'use_strict';

	var FREQUENCY_MAP = {
		'minute': { value: 1,  unit: 'minute'},
		'weekly': { value: 1,  unit: 'week'},
		'biweekly': { value: 2,  unit: 'week'},
		'monthly': { value: 1,  unit: 'month'},
		'2months': { value: 2,  unit: 'month'},
		'quarterly': { value: 3,  unit: 'month'},
		'semiannual': { value: 6,  unit: 'month'},
		'yearly': { value: 1,  unit: 'year'}
	};

	return VaultComponent.extend({
		defaults: { 
			template: 'Fiserv_Payments/payment/commercehub/vault-form',
			commercehubCode: "fiserv_commercehub",
			additionalData: {}
		},

		isSubscriptionEnabled: ko.observable(false),
		selectedFrequency: ko.observable('monthly'),
		frequencyOptions: [
			{ value: 'minute', label: 'Every Minute (test)'},
			{ value: 'weekly', label: 'Weekly'},
			{ value: 'biweekly', label: 'Every 2 Weeks'},
			{ value: 'monthly', label: 'Monthly'},
			{ value: '2months', label: 'Every 2 Months'},
			{ value: 'quarterly', label: 'Every 3 Months'},
			{ value: 'semiannual', label: 'Every 6 Months'},
			{ value: 'yearly', label: 'Yearly'}
		],

		getMaskedCard: function () {
			return this.details.maskedCC.toLowerCase();
		},

		getExpirationDate: function () {
			return this.details.expirationDate;
		},

		getExpirationMonth: function() {
			let idx = this.getExpirationDate().indexOf("/");
			return this.getExpirationDate().substring(0, idx);
		},

		getExpirationYear: function() {
			let idx = this.getExpirationDate().indexOf("/");
			return this.getExpirationDate().substring(idx+1);
		},

		getTokenSource: function () {
			return this.details.tokenSource;
		},

		/**
		 * Place order
		 */
		placeOrderClick: async function () {
			await this.getPaymentMethodToken();
		},

		/**
		 * Send request to get payment method token
		 */
		getPaymentMethodToken: async function () {
			var self = this;
			$.getJSON(self.tokenUrl, {
				'public_hash': self.publicHash
			})
				.done(async (response) => {
					let paymentToken = response.paymentToken;
					if (this.is3DSecureEnabled())
					{
						try {
							fullScreenLoader.startLoader();
							await this.initChSdk(response.paymentToken);
							await this.run3DSecure();
							fullScreenLoader.stopLoader();
						} catch (error) {
							this.threeDSecureFail(error);
							return;
						}
					}
					self.additionalData['payment_token'] = paymentToken;
					self.placeOrder();
				})
				.fail((response) => {
					this.placeOrderFail(response);	
				});
		},

		threeDSecureFail: function(error) {
			fullScreenLoader.stopLoader();
			this.placeOrderFail(error);
		},

		placeOrderFail: function (errorResponse) {
			let error = undefined;
			try {
				error = JSON.parse(errorResponse.responseText);
			} catch (err) {
				error = errorResponse;
			}
			globalMessageList.addErrorMessage({
				message: error.message
			});
		},

		is3DSecureEnabled: function () {
			return window.checkoutConfig.payment[this.getCode()]["threeDSecure"] === '1'
		},

		initChSdk: async function(paymentToken) {
			let credsResponse = await chSession({
				"paymentToken" : paymentToken,
				"threeDSecure" : this.is3DSecureEnabled() 
			});
			let creds = credsResponse["ch_credentials"];
			if (typeof(creds) === "undefined")
			{
				throw new Error("Failed to create payment session.");
			}
			await chAdapter.initSdk(window.checkoutConfig.payment[this.getCode()],creds);	
		},

		run3DSecure: async function() {
			const {transactionState, authenticationTransactionId} = await window.fiserv.components.threeDSecure();
			if (transactionState.toUpperCase() === "DECLINED") {
				throw new Error("3D-Secure authentication failure.");
			}

			this.handle3DSecureAuth(authenticationTransactionId);
		},

		handle3DSecureAuth: function (threeDSId) {
			this.additionalData["3DSecureId"] = threeDSId;
		},

		/**
		 * Get payment method data
		 * @returns {Object}
		 */
		getData: function () {
			var data = {
				'method': this.code,
				'additional_data': {
					'public_hash': this.publicHash,
					'token_source': this.getTokenSource(),
					'expiration_month': this.getExpirationMonth(),
					'expiration_year': this.getExpirationYear()
				}
			};

			data['additional_data'] = _.extend(data['additional_data'], this.additionalData);

			// Inject subscription data when the customer has opted in
			if (this.isSubscriptionEnabled()) {
				var freq = FREQUENCY_MAP[this.selectedFrequency()] || { value: 1, unit: 'month' };
				data['additional_data']['is_subscription'] = true;
				data['additional_data']['subscription_interval_value'] = freq.value;
				data['additional_data']['subscription_interval_unit'] = freq.unit;
			}

			return data;
		},

		/**
		 * Retrieve the fiserv_subscription child component if it exists.
		 */
		getSubscriptionComponent: function () {
			try {
				var region = this.getRegion('subscription');
				if (region && region()) {
					return region()[0] || null;
				}
			} catch (e) {}
			return null;
		},

		/**
		 * Get payment name
		 *
		 * @returns {String}
		 */
		getCode: function () {
			return this.commercehubCode;
		}
	});
});
