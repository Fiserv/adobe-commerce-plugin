define([
	'jquery',
	'Magento_Vault/js/view/payment/method-renderer/vault',
	'Magento_Ui/js/model/messageList',
	'Magento_Checkout/js/model/full-screen-loader'
], function(
	$,
	VaultComponent,
	globalMessageList,
	fullScreenLoader
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
		placeOrderClick: function () {
			this.getPaymentMethodToken();
		},

		/**
		 * Send request to get payment method token
		 */
		getPaymentMethodToken: function () {
			var self = this;
			fullScreenLoader.startLoader();
			$.getJSON(self.tokenUrl, {
				'public_hash': self.publicHash
			})
				.done(function (response) {
					fullScreenLoader.stopLoader();
					self.additionalData['payment_token'] = response.paymentToken;
					self.placeOrder();
				})
				.fail(function (response) {
					var error = JSON.parse(response.responseText);

					fullScreenLoader.stopLoader();
					globalMessageList.addErrorMessage({
						message: error.message
					});
				});
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
		}

	});
});
