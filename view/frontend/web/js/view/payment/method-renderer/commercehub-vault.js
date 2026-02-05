define([
	'jquery',
	'Magento_Vault/js/view/payment/method-renderer/vault',
	'Magento_Ui/js/model/messageList',
	'Magento_Checkout/js/model/full-screen-loader',
	'Fiserv_Payments/js/ch-adapter',
	'Fiserv_Payments/js/action/create-commercehub-enriched-session',
], (
	$,
	VaultComponent,
	globalMessageList,
	fullScreenLoader,
	chAdapter,
	chSession,
) => {
	'use_strict';

	return VaultComponent.extend({
		defaults: {
			template: 'Fiserv_Payments/payment/commercehub/vault-form',
			commercehubCode: 'fiserv_commercehub',
			additionalData: {},
		},

		getMaskedCard() {
			return this.details.maskedCC.toLowerCase();
		},

		getExpirationDate() {
			return this.details.expirationDate;
		},

		getExpirationMonth() {
			const index = this.getExpirationDate().indexOf('/');

			return this.getExpirationDate().slice(0, Math.max(0, index));
		},

		getExpirationYear() {
			const index = this.getExpirationDate().indexOf('/');

			return this.getExpirationDate().slice(Math.max(0, index + 1));
		},

		getTokenSource() {
			return this.details.tokenSource;
		},

		/**
		 * Place order
		 */
		async placeOrderClick() {
			await this.getPaymentMethodToken();
		},

		/**
		 * Send request to get payment method token
		 */
		async getPaymentMethodToken() {
			const self = this;

			$.getJSON(self.tokenUrl, {
				public_hash: self.publicHash,
			})
				.done(async (response) => {
					const { paymentToken } = response;

					if (this.is3DSecureEnabled()) {
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
					self.additionalData.payment_token = paymentToken;
					self.placeOrder();
				})
				.fail((response) => {
					this.placeOrderFail(response);
				});
		},

		threeDSecureFail(error) {
			fullScreenLoader.stopLoader();
			this.placeOrderFail(error);
		},

		placeOrderFail(errorResponse) {
			let error;

			try {
				error = JSON.parse(errorResponse.responseText);
			} catch {
				error = errorResponse;
			}
			globalMessageList.addErrorMessage({
				message: error.message,
			});
		},

		is3DSecureEnabled() {
			return globalThis.checkoutConfig.payment[this.getCode()].threeDSecure === '1';
		},

		async initChSdk(paymentToken) {
			const credsResponse = await chSession({
				paymentToken,
				threeDSecure: this.is3DSecureEnabled(),
			});
			const creds = credsResponse.ch_credentials;

			if (creds === undefined) {
				throw new TypeError('Failed to create payment session.');
			}
			await chAdapter.initSdk(globalThis.checkoutConfig.payment[this.getCode()], creds);
		},

		async run3DSecure() {
			const { transactionState, authenticationTransactionId } = await globalThis.fiserv.components.threeDSecure();

			if (transactionState.toUpperCase() === 'DECLINED') {
				throw new Error('3D-Secure authentication failure.');
			}

			this.handle3DSecureAuth(authenticationTransactionId);
		},

		handle3DSecureAuth(threeDSId) {
			this.additionalData['3DSecureId'] = threeDSId;
		},

		/**
		 * Get payment method data
		 * @returns {Object}
		 */
		getData() {
			const data = {
				method: this.code,
				additional_data: {
					public_hash: this.publicHash,
					token_source: this.getTokenSource(),
					expiration_month: this.getExpirationMonth(),
					expiration_year: this.getExpirationYear(),
				},
			};

			data.additional_data = _.extend(data.additional_data, this.additionalData);

			return data;
		},

		/**
		 * Get payment name
		 *
		 * @returns {String}
		 */
		getCode() {
			return this.commercehubCode;
		},
	});
});
