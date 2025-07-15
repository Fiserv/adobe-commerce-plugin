define([
	'jquery',
	'Magento_Vault/js/view/payment/method-renderer/vault',
	'Magento_Ui/js/model/messageList',
	'Magento_Checkout/js/model/full-screen-loader',
	'Fiserv_Payments/js/ch-adapter',
	'Fiserv_Payments/js/action/create-commercehub-session'
], function(
	$,
	VaultComponent,
	globalMessageList,
	fullScreenLoader,
	chAdapter,
	chSession
){
	'use_strict';

	return VaultComponent.extend({
		defaults: { 
			template: 'Fiserv_Payments/payment/commercehub/vault-form',
			commercehubCode: "fiserv_commercehub",
			additionalData: {}
		},

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
			fullScreenLoader.startLoader();
			$.getJSON(self.tokenUrl, {
				'public_hash': self.publicHash
			})
				.done(async (response) => {
					let paymentToken = response.paymentToken;
					if (this.is3DSecureEnabled() && false)
					{
						try {
							await this.initChSdk(response.paymentToken);
							await this.run3DSecure();
						} catch (error) {
							this.placeOrderFail(error);
							return;
						}
					}
					self.additionalData['payment_token'] = paymentToken;
					self.placeOrder();
				})
				.fail((response) => {
					this.placeOrderFail(response);
				})
				.always(() => {
					fullScreenLoader.stopLoader(); // Ensure the loader always stops
				});
		},

		placeOrderFail: function (errorResponse) {
			let error = undefined;
			try {
				error = JSON.parse(errorResponse.responseText);
			} catch (err) {
				error = errorResponse;
			}
			fullScreenLoader.stopLoader();
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
			await chAdapter.initSdk(window.checkoutConfig.payment[this.getCode()],creds);	
		},

		run3DSecure: async function() {
			const {transactionState, authenticationTransactionId} = await window.fiserv.components.threeDSecure();
			if (transactionState !== "AUTHENTICATED") {
				throw new Error("3DS authentication failed");
			}

			this.handle3DSecureAuth(authenticationTransactionId);
		},

		handle3DSecureAuth: function (threeDSId) {
			this.additionalData["threeDSecureId"] = threeDSId;
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

			return data;
		},

		/**
		 * Show Privacy statement
		 */
		showPrivacyStatement: function() 
		{
			return window.checkoutConfig.payment[this.getCode()].show_privacy_statement;
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
