define([
	'underscore',
	'jquery',
	'Magento_Vault/js/view/payment/method-renderer/vault',
	'Magento_Ui/js/model/messageList',
	'Magento_Checkout/js/model/full-screen-loader',
	'Fiserv_Payments/js/ch-adapter',
	'Fiserv_Payments/js/action/create-commercehub-enriched-session'
], function(
	_,
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
			additionalData: {},
			paymentPayload: { sessionId: null },
			paymentMethodName: '[name="payment[method]"]',
			isIframeValid: false,
			securityIframeInitialized: false
		},

		initialize: function () {
			this._super();
			this.setupPaymentMethodWatcher();
			return this;
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

		setupPaymentMethodWatcher: function () {
			$(document).on("click", this.paymentMethodName, (event) => {
				if (event.currentTarget.id === this.getId()) {
					this.loadSecurityIframe();
				} else {
					this.destroySecurityIframe();
				}
			});
		},

		initSecurityIframe: function () {
			if (this.securityIframeInitialized) {
				return;
			}
			if (this.isChecked() === this.getId()) {
				this.loadSecurityIframe();
			}
			this.securityIframeInitialized = true;
		},

		prepareChAdapter: function () {
			chAdapter.initialize(
				window.checkoutConfig.payment[this.getCode()],
				() => { this.iframeLoadSuccess(); },
				(valid) => { this.iframeValidHandler(valid); },
				() => {},
				(data) => { this.fieldValidityHandler(data); },
				(data) => { this.fieldFocusHandler(data); }
			);
		},

		buildSecurityCodeFormConfig: function () {
			const baseConfig = window.checkoutConfig.payment[this.getCode()].formConfig;
			const clonedConfig = JSON.parse(JSON.stringify(baseConfig));
			clonedConfig.fields = {
				securityCode: { ...baseConfig.fields.securityCode, parentElementId: this.getId() + '-security-code' }
			};
			clonedConfig.environment = window.checkoutConfig.payment[this.getCode()].environment;
			return clonedConfig;
		},

		loadSecurityIframe: function () {
			this.prepareChAdapter();
			const frameConfig = { data: this.buildSecurityCodeFormConfig() };
			this.beginIframeFlow();
			chAdapter.destroyIframe();
			chAdapter.instantiateIframe(
				() => { this.iframeLoadSuccess(); },
				(error) => { this.iframeLoadFailure(error); },
				frameConfig
			);
		},

		destroySecurityIframe: function () {
			chAdapter.destroyIframe();
			this.isIframeValid = false;
		},

		checkSecurityMask: function() {
			let maskingMode = window.checkoutConfig.payment[this.getCode()].formConfig["fields"]["securityCode"]["masking"]["mode"];
			return maskingMode !== "NO_MASKING";
		},

		getSecurityUnmaskButton: function() {
			return $('button#' + this.getId() + '-sdc-unmask-security');
		},

		getSecurityMaskButton: function() {
			return $('button#' + this.getId() + '-sdc-mask-security');
		},

		unmaskSecurityCode: function() {
			chAdapter.unmask('securityCode');
			let unmaskButton = this.getSecurityUnmaskButton();
			let maskButton = this.getSecurityMaskButton();
			unmaskButton.addClass('sdc-hidden');
			maskButton.removeClass('sdc-hidden');
		},

		maskSecurityCode: function() {
			chAdapter.mask('securityCode');
			let unmaskButton = this.getSecurityUnmaskButton();
			let maskButton = this.getSecurityMaskButton();
			unmaskButton.removeClass('sdc-hidden');
			maskButton.addClass('sdc-hidden');
		},

		iframeValidHandler: function(valid) {
			this.isIframeValid = valid;
		},

		getSdcFieldFrame: function() {
			return $('#' + this.getId() + '-sdc-security-code-frame');
		},

		getSdcFieldInvalidMessageContainer: function() {
			return $('#' + this.getId() + '-sdc-security-code-invalid-message');
		},

		getInvalidFieldMessages: function() {
			return window.checkoutConfig.payment[this.getCode()].invalidFields;
		},

		getSdcInvalidFieldMessageText: function() {
			return this.getInvalidFieldMessages()["securityCode"];
		},

		fieldValidityHandler: function(data) {
			if (data["field"] !== "securityCode") {
				return;
			}
			let frame = this.getSdcFieldFrame();
			let mess = this.getSdcFieldInvalidMessageContainer();
			if (data["isValid"] === true) {
				frame.removeClass('sdc-error-field');
				frame.addClass('sdc-valid-field');
				mess.addClass('sdc-hidden');
			} else if (data["shouldShowError"] === true) {
				mess.text(this.getSdcInvalidFieldMessageText());
				frame.removeClass('sdc-valid-field');
				frame.addClass('sdc-error-field');
				mess.removeClass('sdc-hidden');
			} else {
				frame.removeClass('sdc-valid-field');
				frame.removeClass('sdc-error-field');
				mess.addClass('sdc-hidden');
			}
		},

		fieldFocusHandler: function(data) {
			let frame = this.getSdcFieldFrame();
			if(frame.length) {
				if(frame[0].contains(document.activeElement) === true) {
					frame.addClass('sdc-focused-field');
				} else {
					frame.removeClass('sdc-focused-field');
				}
			}
		},

		beginIframeFlow: function () {
			fullScreenLoader.startLoader();
		},

		endIframeFlow: function () {
			fullScreenLoader.stopLoader();
		},

		setPaymentPayload: function (sessionId) {
			this.paymentPayload.sessionId = sessionId;
		},

		showError: function (errorMessage) {
			globalMessageList.addErrorMessage({
				message: errorMessage
			});
		},

		iframeLoadSuccess: function () {
			this.endIframeFlow();
		},

		iframeLoadFailure: function (message) {
			this.endIframeFlow();
			this.showError(message);
		},

		iframeRunFailure: function (message) {
			this.showError(message || "Card capture failure. Please try again.");
			this.endIframeFlow();
			this.isIframeValid = false;
		},

		submitSecurityCode: async function () {
			return new Promise((resolve, reject) => {
				this.beginIframeFlow();
				chAdapter.submitCardForm(
					window.checkoutConfig.payment.fiserv_payments['storeUrl'],
					(sessionId) => { this.setPaymentPayload(sessionId); this.endIframeFlow(); resolve(sessionId); },
					(error) => { this.iframeRunFailure(error); reject(error); }
				);
			});
		},

		isButtonActive: function () {
			return this.isIframeValid && this._super();
		},

		/**
		 * Place order
		 */
		placeOrderClick: async function () {
			if (!this.isIframeValid) {
				this.showError("Please enter a valid security code.");
				return;
			}
			try {
				await this.submitSecurityCode();
			} catch (error) {
				return;
			}
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
			if (this.paymentPayload.sessionId) {
				data['additional_data']['payment_session'] = this.paymentPayload.sessionId;
			}

			data['additional_data'] = _.extend(data['additional_data'], this.additionalData);

			return data;
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
