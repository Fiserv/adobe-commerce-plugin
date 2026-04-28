define([
	'underscore',
	'jquery',
	'ko',
	'Magento_Vault/js/view/payment/method-renderer/vault',
	'Magento_Ui/js/model/messageList',
	'Magento_Checkout/js/model/full-screen-loader',
	'Fiserv_Payments/js/ch-adapter',
	'Fiserv_Payments/js/action/create-commercehub-enriched-session'
], function(
	_,
	$,
	ko,
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
			securityIframeInitialized: false
		},

		initialize: function () {
			this._super();
			if (!this.shouldRequireVaultCvv()) {
				this.updatePlaceOrderState(true);
			}
			this.setupPaymentMethodWatcher();
			return this;
		},

		shouldRequireVaultCvv: function () {
			const paymentCfg = window.checkoutConfig && window.checkoutConfig.payment && window.checkoutConfig.payment[this.getCode()];
			if (!paymentCfg || typeof paymentCfg.vaultUseCvv === 'undefined') {
				return false;
			}
			return paymentCfg.vaultUseCvv === true || paymentCfg.vaultUseCvv === '1' || paymentCfg.vaultUseCvv === 1;
		},

		getPlaceOrderButton: function () {
			return $('#' + this.getId() + '-place-order');
		},

		updatePlaceOrderState: function (isEnabled) {
			let button = this.getPlaceOrderButton();
			if (button.length) {
				button.prop('disabled', !isEnabled);
			}
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
					if (!this.shouldRequireVaultCvv()) {
						this.updatePlaceOrderState(true);
					} else {
						this.loadSecurityIframe();
					}
				} else {
					this.destroySecurityIframe();
				}
			});
		},

		initSecurityIframe: function () {
			if (this.securityIframeInitialized) {
				return;
			}

			if (!this.shouldRequireVaultCvv()) {
				return;
			}

			if (this.isChecked() === this.getId()) {
				this.loadSecurityIframe();
			}
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
				securityCode: {
					...baseConfig.fields.securityCode,
					parentElementId: this.getId() + '-security-code',
					brandId: this.mapCardTypeToBrandId(this.details.type)
				}
			};
			clonedConfig.environment = window.checkoutConfig.payment[this.getCode()].environment;
			return clonedConfig;
		},

		loadSecurityIframe: function () {
			window.fiservVaultIframeGuards = window.fiservVaultIframeGuards || {};
			var guardKey = this.getId();
			if (window.fiservVaultIframeGuards[guardKey]) {
				return;
			}
			window.fiservVaultIframeGuards[guardKey] = true;
			this.securityIframeInitialized = true;
			this.updatePlaceOrderState(false);
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
			this.updatePlaceOrderState(false);
			if (window.fiservVaultIframeGuards) {
				delete window.fiservVaultIframeGuards[this.getId()];
			}
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
			this.updatePlaceOrderState(valid);
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
				this.updatePlaceOrderState(true);
				frame.removeClass('sdc-error-field');
				frame.addClass('sdc-valid-field');
				mess.addClass('sdc-hidden');
			} else if (data["shouldShowError"] === true) {
				this.updatePlaceOrderState(false);
				mess.text(this.getSdcInvalidFieldMessageText());
				frame.removeClass('sdc-valid-field');
				frame.addClass('sdc-error-field');
				mess.removeClass('sdc-hidden');
			} else {
				this.updatePlaceOrderState(false);
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
			this.updatePlaceOrderState(false);
		},

		submitSecurityCode: async function (credentials) {
			return new Promise((resolve, reject) => {
				this.beginIframeFlow();
				chAdapter.submitCardForm(
					window.checkoutConfig.payment.fiserv_payments['storeUrl'],
					(sessionId) => { this.setPaymentPayload(sessionId); this.endIframeFlow(); resolve(sessionId); },
					(error) => { this.iframeRunFailure(error); reject(error); },
					credentials
				);
			});
		},

		/**
		 * Place order
		 */
		placeOrderClick: async function () {

			fullScreenLoader.startLoader();

			try {
				this.paymentToken = await this.getPaymentMethodToken();
			} catch (error) {
				this.showError(error.message);	
				return;
			}

			if (this.shouldRequireVaultCvv()) {
				await this.setCredentials(this.paymentToken);

				try {
					await this.submitSecurityCode(this.credentials);
				} catch (error) {
					return;
				}
			}

			if (this.is3DSecureEnabled()) {
				try {
					await this.begin3DSecureFlow()
				} catch (error) {
					this.threeDSecureFail(error);
					fullScreenLoader.stopLoader();
					return;
				}
			}

			this.additionalData['payment_token'] = this.paymentToken;
			
			fullScreenLoader.stopLoader();

			this.placeOrder();
		},

		/**
		 * Send request to get payment method token
		 */
		getPaymentMethodToken: async function () {
			const response = await fetch(`${ this.tokenUrl }?public_hash=${ this.publicHash }`,
				{
					method: "GET",
					headers: { "Accept": "application/json" },
				}
			);

			if (!response.ok) throw new Error(`Error retrieving payment token: ${ response.statusText }`);
			
			const responseBody = await response.json();

			return responseBody.paymentToken
		},

		begin3DSecureFlow: async function(sessionId) {
			await this.initChSdk(this.paymentToken);
			await this.run3DSecure();
		},

		threeDSecureFail: function(error) {
			this.placeOrderFail(error);
			this.paymentToken = undefined;
			this.credentials = undefined;
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

		setCredentials: async function(paymentToken) {
			const credsResponse = await chSession({
				"paymentToken" : paymentToken,
				"threeDSecure" : this.is3DSecureEnabled() 
			});

			this.credentials = credsResponse["ch_credentials"];
		},
			

		initChSdk: async function(paymentToken) {
			await this.setCredentials(paymentToken);
			if (!this.credentials) throw new Error("Failed to create payment session.");

			await chAdapter.initSdk(window.checkoutConfig.payment[this.getCode()], this.credentials);	
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
			if (this.shouldRequireVaultCvv() && this.paymentPayload.sessionId) {
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
		},

		mapCardTypeToBrandId: function(cardType)
		{
			if (!cardType) return null;

			switch (cardType) {
				case 'VI':
					return 'visa';
				case 'MC':
					return 'mastercard';
				case 'AE':
					return 'american-express';
				case 'MD':
					return 'maestro';
				case 'DI':
					return 'discover';
				default:
					return null;
			}
		}
	});
});
