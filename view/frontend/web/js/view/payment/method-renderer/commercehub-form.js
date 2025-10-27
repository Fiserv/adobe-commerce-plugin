/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define(
	[
		'underscore',
		'jquery',
		'Magento_Payment/js/view/payment/cc-form',
		'Fiserv_Payments/js/ch-adapter',
		'Fiserv_Payments/js/action/create-commercehub-session',
		'Magento_Checkout/js/model/quote',
		'Magento_Checkout/js/checkout-data',
		'Magento_Ui/js/model/messageList',
		'Magento_Vault/js/view/payment/vault-enabler',
		'Magento_Checkout/js/model/full-screen-loader',
		'Magento_Checkout/js/model/payment/additional-validators',
		'Fiserv_Payments/js/action/modify-requirejs',
		'ko',
		'mage/translate',
		'domReady!'
	],
	function (
		_,
		$,
		Component,
		chAdapter,
		chSession,
		quote,
		checkoutData,
		globalMessageList,
		VaultEnabler,
		fullScreenLoader,
		additionalValidators,
		modifyRequirejs,
		ko,
		$t
	) {
		'use strict';

		return Component.extend({
			isPlaceOrderActionAllowed: ko.observable(quote.billingAddress() != null),
			defaults: {
				template: 'Fiserv_Payments/payment/commercehub/form',
				active: false,
				code: 'fiserv_commercehub',
				paymentPayload: {
					sessionId: null,
					type: null,
					threeDSecureId: undefined
				},
				additionalData: {},
				paymentMethodName: '[name="payment[method]"',
				isIframeValid: false,
				credentials: undefined
			},

			/**
			 * @returns {exports.initialize}
			 */
			initialize: async function () {
					quote.billingAddress.subscribe(function (address) {
					this.isPlaceOrderActionAllowed(address !== null);
					this.checkoutValidHandler();	
				}, this);
				
				var self = this;

				self._super();
				self.vaultEnabler = new VaultEnabler();
				self.vaultEnabler.setPaymentCode(self.getVaultCode());
			
				chAdapter.initialize(
					window.checkoutConfig.payment[self.code],
					self.iframeLoadSuccess.bind(self),
					self.iframeValidHandler.bind(self),
					(brand) => { this.cardBrandChangeHandler(brand); },
					(data) => { this.fieldValidityHandler(data); },
					(data) => { this.fieldFocusHandler(data); }
				);

				return self;
			},

			loadSdcForm: function () {
				if (this.isChecked() === this.code) {
					this.loadIframe();
				}
			},

			initCheckoutSdk: async function(credentials)
			{
				await chAdapter.initSdk(
					window.checkoutConfig.payment[this.code],
					credentials
				);
			},

			loadIframe: function () {
				chAdapter.destroyIframe();
				this.cardBrandChangeHandler(null);

				var self = this;
				let failureCb = this.iframeLoadFailure.bind(this);

				let iframePromise = new Promise((resolve, reject) => {
					this.beginIframeFlow();
					chAdapter.instantiateIframe(
						resolve, 
						reject,
					)
				});
				iframePromise.then((data) => {
				}).catch((error) =>{
					failureCb(error);
				})
			},

			iframeLoadSuccess: function (data) {
				this.endIframeFlow();
			},

			iframeRunSuccess: async function (sessionId) {
				this.setPaymentPayload(sessionId);
				
				try {
					if (this.is3DSecureEnabled()) {
						await this.run3DSecure();
					}
				} catch (error) {
					this.iframeRunFailure(error.message);
					this.endIframeFlow();
					return;
				}
				this.endIframeFlow();
				this.placeOrderClick();
			},

			run3DSecure: async function () {
				let runStates = ["AUTHENTICATED", "WAITING"]; 
				const {transactionState, authenticationTransactionId} = await window.fiserv.components.threeDSecure();
				if (!runStates.includes(transactionState.toUpperCase())) {
					throw new Error("3D Secure payment authentication failure.");
				}

				this.handle3DSecureAuth(authenticationTransactionId);
			},

			handle3DSecureAuth: function (threeDSId) {
				this.set3DSecurePayload(threeDSId);	
			},

			is3DSecureEnabled: function()
			{
				return window.checkoutConfig.payment[this.code]["threeDSecure"] === '1'
			},

			iframeLoadFailure: function (message) {
				this.endIframeFlow();
				this.showError(message);
			},

			iframeRunFailure: function (message) {
				this.showError(message ?? "Card capture failure. Please try again."); 
				this.endIframeFlow();
				this.cardBrandChangeHandler(null);
				chAdapter.resetIframe();
			},

			beginIframeFlow: function () {
				fullScreenLoader.startLoader();
			},

			endIframeFlow: function () {
				fullScreenLoader.stopLoader();
			},

			/**
			 * Set list of observable attributes
			 *
			 * @returns {exports.initObservable}
			 */
			initObservable: function () {

				this._super()
					.observe(['active']);

				return this;
			},
			
			/** 
			 * Deactivates card form when fiserv_commercehub not checked.
			 * isActive() not working with COD, for some reason.
			 */
			watchPaymentMethods: function () {
				let self = this;
				$(self.paymentMethodName).on("click", function() {
					let selected = $(this).attr("id");
					if (selected === self.getCode()) {
						self.loadIframe();	
					} else {
						self.cardBrandChangeHandler(null);
						chAdapter.destroyIframe();
					}
				});
			},

			refreshBillingAddress: function () {
				if (this.isAchActive() && quote.billingAddress()) {
					shpfUtils.setBillingAddress(quote.billingAddress());
					this.initAchIframe();
				}
			},

			/**
			 * Get payment name
			 *
			 * @returns {String}
			 */
			getCode: function () {
				return this.code;
			},

			/**
			 * @returns {Boolean}
			 */
			isVaultEnabled: function () {
				return this.vaultEnabler.isVaultEnabled();
			},

			/**
			 * Returns vault code.
			 *
			 * @returns {String}
			 */
			getVaultCode: function () {
				return window.checkoutConfig.payment[this.getCode()].vaultCode;
			},

			getInvalidFieldMessages: function() {
				return window.checkoutConfig.payment[this.getCode()].invalidFields;
			},

			/**
			 * Get BluePay Gateway Environment
			 *
			 * @returns {String}
			 */
			getEnvironment: function () {
				return window.checkoutConfig.payment[this.getCode()].environment;
			},

			/**
			 * Get billing address
			 *
			 * @returns {String}
			 */
			getBillingAddress: function () {
				let billingAddress = checkoutData.getBillingAddressFromData();
				if (!billingAddress) {
					billingAddress = quote.billingAddress();
				}
				return billingAddress;
			},

			/**
			 * Check if payment is active
			 *
			 * @returns {Boolean}
			 */
			isActive: function () {
				let active = this.getCode() === this.isChecked();

				this.active(active);

				return active;
			},

			/**
			 * Get data
			 *
			 * @returns {Object}
			 */
			getData: function () {
				var data = {
					'method': this.getCode(),
					'additional_data': {
						'payment_session': this.paymentPayload.sessionId
					}
				};

				if (typeof(this.paymentPayload.threeDSecureId) !== "undefined") {
					data['additional_data']['3DSecureId'] = this.paymentPayload.threeDSecureId;
				}

				data['additional_data'] = _.extend(data['additional_data'], this.additionalData);
				this.vaultEnabler.visitAdditionalData(data);

				return data;
			},

			/**
			 * Get list of available CC types
			 *
			 * @returns {Object}
			 */
			getCcAvailableTypes: function () {  
				return validator.getAvailableCardTypes();
			},

			/**
			 * Action to place order
			 */
			placeOrder: function (key) {
				var self = this;

				if (key) {
					// handle payment failure. Reinit Iframe
					$(document).one("ajaxError", (ev,xhr) => { this.placeOrderFailureHandler(ev,xhr); } );
					return self._super();
				}
			
				return false;
			},

			placeOrderFailureHandler: function (ev,xhr) {
				chAdapter.destroyIframe();
				this.loadIframe();
				this.cardBrandChangeHandler(null);
				this.resetFieldValidation();
			},

			/**
			 * Trigger order placing
			 */
			placeOrderClick: function () {
				if (this.paymentPayload.sessionId) {
					this.placeOrder('parent');
				}
			},

			setPaymentTokenInfo: function (token) {
				this.setPaymentPayload(token);
			},

			validateCardType: function (brand) {
				let result = false;
				let mapper = pjsAdapter.getCcTypesMapper();
				if (Object.keys(mapper).length) {
					let ccType = mapper[brand.toUpperCase()];
					if (ccType) {
						if (this.getCcAvailableTypes().include(ccType)) {
							result = true;
						}
					}
				}
				return result;
			},

			/**
			 * Sets payment sessionId
			 *
			 * @param {Object} paymentToken
			 * @private
			 */	
			setPaymentPayload: function (sessionId) {
				this.paymentPayload.sessionId = sessionId;
			},

			set3DSecurePayload: function (threeDSecureId) {
				this.paymentPayload.threeDSecureId = threeDSecureId;
			},

			/**
			 * Show error message
			 *
			 * @param {String} errorMessage
			 * @private
			 */
			showError: function (errorMessage) {
				globalMessageList.addErrorMessage({
					message: errorMessage
				});
			},

			enableSubmitButton: function() {
				this.getSubmitButton().prop("disabled", false);
			},

			disableSubmitButton: function() {
				this.getSubmitButton().prop("disabled", true);
			},

			getSubmitButton: function() {
				return $('button#fiserv-checkout-submit');
			},

			submitIframe: async function() {
				if (this.isPlaceOrderActionAllowed() === true && additionalValidators.validate()) {
					fullScreenLoader.startLoader();
					let credsResponse = undefined;	
					try {	
						credsResponse = await chSession( { "threeDSecure" : this.is3DSecureEnabled() } );
					} catch (error) {
						console.log("An error occurred while starting Commercehub payment session: ".concat(error));
						this.iframeRunFailure();
						return;
					}
					let creds = credsResponse["ch_credentials"];
					
					// handle 3DS
					if(this.is3DSecureEnabled())
					{
						await this.initCheckoutSdk(creds);
					}
						
					chAdapter.submitCardForm(
						window.checkoutConfig.payment.fiserv_payments['storeUrl'], 
						(sessionId) => { this.iframeRunSuccess(sessionId); },
						() => { this.iframeRunFailure(); },
						creds
					);
				}
			},

			getNumberUnmaskButton: function() {
				return $('button#sdc-unmask-number');
			},

			getNumberMaskButton: function() {
				return $('button#sdc-mask-number');
			},

			getSecurityUnmaskButton: function() {
				return $('button#sdc-unmask-security');
			},

			getSecurityMaskButton: function() {
				return $('button#sdc-mask-security');
			},

			/**
			 * Checks masking mode
			 *
			 * @returns {Boolean}
			 */
			checkNumberMask: function() {
				let maskingMode = window.checkoutConfig.payment[this.getCode()].formConfig["fields"]["cardNumber"]["masking"]["mode"];
				if (maskingMode == "NO_MASKING") {
					return false;
				} else {
					return true;
				}
			},

			checkSecurityMask: function() {
				let maskingMode = window.checkoutConfig.payment[this.getCode()].formConfig["fields"]["securityCode"]["masking"]["mode"];
				if (maskingMode == "NO_MASKING") {
					return false;
				} else {
					return true;
				}
			},

			unmaskCardNumber: function() {
				chAdapter.unmask('cardNumber');
				let unmaskButton = this.getNumberUnmaskButton();
				let maskButton = this.getNumberMaskButton();
				unmaskButton.addClass('sdc-hidden');
				maskButton.removeClass('sdc-hidden');
			},

			maskCardNumber: function() {
				chAdapter.mask('cardNumber');
				let unmaskButton = this.getNumberUnmaskButton();
				let maskButton = this.getNumberMaskButton();
				unmaskButton.removeClass('sdc-hidden');
				maskButton.addClass('sdc-hidden');
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
				this.checkoutValidHandler();
			},

			checkoutValidHandler: function() {
				if (this.isIframeValid === true && this.isPlaceOrderActionAllowed() === true) {
					this.enableSubmitButton();
				} else {
					this.disableSubmitButton();
				}
			},

			getCardBrandIcon: function() {
				return $('#sdc-card-brand-icon');
			},

			setCardBrandIconClass: function(cssClass) {
				let icon = this.getCardBrandIcon();
				icon.removeClass();
				icon.addClass('sdc-card-brand-icon');
				if (typeof(cssClass) !== "undefined")
				{
					icon.addClass(cssClass);
				}
			},

			cardBrandChangeHandler: function(brand) {
				let icon = this.getCardBrandIcon();

				switch (brand) {
					case null:
						this.setCardBrandIconClass();
						break;
					case 'visa':
						this.setCardBrandIconClass('sdc-card-brand-icon-visa');
						break;
					case 'mastercard':
						this.setCardBrandIconClass('sdc-card-brand-icon-mastercard');
						break;
					case 'american-express':
						this.setCardBrandIconClass('sdc-card-brand-icon-amex');
						break;
					case 'diners-club':
						this.setCardBrandIconClass('sdc-card-brand-icon-diners');
						break;
					case 'discover':
						this.setCardBrandIconClass('sdc-card-brand-icon-discover');
						break;
					case 'jcb':
						this.setCardBrandIconClass('sdc-card-brand-icon-jcb');
						break;
					case 'unionpay':
						this.setCardBrandIconClass('sdc-card-brand-icon-union');
						break;
					case 'maeestro':
						this.setCardBrandIconClass('sdc-card-brand-icon-maeestro');
						break;
					case 'elo':
						this.setCardBrandIconClass('sdc-card-brand-icon-elo');
						break;
				}
			},


			getSdcFieldFrame: function(name) {
				switch(name)
				{
					case "cardNumber":
						return $('#sdc-card-number-frame');
					case "nameOnCard":
						return $('#sdc-card-name-frame');
					case "securityCode":
						return $('#sdc-security-code-frame');
					case "expirationMonth":
						return $('#sdc-exp-month-frame');
					case "expirationYear":
						return $('#sdc-exp-year-frame');
				}

				return undefined;
			},

			getSdcFieldInvalidMessageContainer: function(name) {
				switch(name)
				{
					case "cardNumber":
						return $('#sdc-card-number-invalid-message');
					case "nameOnCard":
						return $('#sdc-card-name-invalid-message');
					case "securityCode":
						return $('#sdc-security-code-invalid-message');
					case "expirationMonth":
						return $('#sdc-exp-month-invalid-message');
					case "expirationYear":
						return $('#sdc-exp-year-invalid-message');
				}

				return undefined;
				
			},

			getSdcInvalidFieldMessageText: function(name) {
				switch(name)
				{
					case "cardNumber":
						return this.getInvalidFieldMessages()["cardNumber"];
					case "nameOnCard":
						return this.getInvalidFieldMessages()["nameOnCard"];
					case "securityCode":
						return this.getInvalidFieldMessages()["securityCode"];
					case "expirationMonth":
						return this.getInvalidFieldMessages()["expirationMonth"];
					case "expirationYear":
						return this.getInvalidFieldMessages()["expirationYear"];
				}

				return "";
	
			},

			getSdcFields: function() {
				return $('.sdc-field-frame');
			},

			resetFieldValidation : function() {

				let fields = this.getSdcFields();

				fields.each (
					function(index) {
						$(this).removeClass('sdc-valid-field');
					}
				);
			},

			fieldValidityHandler: function(data) {
				let frame = this.getSdcFieldFrame(data["field"]);
				let mess = this.getSdcFieldInvalidMessageContainer(data["field"]);

				if (typeof(frame) !== "undefined")
				{
					if (data["isValid"] === true) 
					{
						frame.removeClass('sdc-error-field');
						frame.addClass('sdc-valid-field');
						mess.addClass('sdc-hidden');
					} else if (data["shouldShowError"] === true)
					{
						mess.text(this.getSdcInvalidFieldMessageText(data["field"]));
						frame.removeClass('sdc-valid-field');
						frame.addClass('sdc-error-field');
						mess.removeClass('sdc-hidden');
					} else
					{
						frame.removeClass('sdc-valid-field');
						frame.removeClass('sdc-error-field');
						mess.addClass('sdc-hidden');
					}
				}
			},

			fieldFocusHandler: function(data) {
				let frame = this.getSdcFieldFrame(data);
				
				if(typeof(frame) !== "undefined") {
					if(frame[0].contains(document.activeElement) === true) {
						frame.addClass('sdc-focused-field');
					} else
					{
						frame.removeClass('sdc-focused-field');
					}
				}
			}
		});
	}
);
