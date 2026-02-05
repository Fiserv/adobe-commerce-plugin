/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
/* browser:true */
/* global define */
define(
	[
		'underscore',
		'jquery',
		'Magento_Payment/js/view/payment/cc-form',
		'Fiserv_Payments/js/ch-adapter',
		'Fiserv_Payments/js/action/create-commercehub-enriched-session',
		'Magento_Checkout/js/model/quote',
		'Magento_Checkout/js/checkout-data',
		'Magento_Ui/js/model/messageList',
		'Magento_Vault/js/view/payment/vault-enabler',
		'Magento_Checkout/js/model/full-screen-loader',
		'Magento_Checkout/js/model/payment/additional-validators',
		'Fiserv_Payments/js/action/modify-requirejs',
		'ko',
		'mage/translate',
		'domReady!',
	],
	(
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
		$t,
	) => {
		'use strict';

		return Component.extend({
			isPlaceOrderActionAllowed: ko.observable(quote.billingAddress() != undefined),
			defaults: {
				template: 'Fiserv_Payments/payment/commercehub/form',
				active: false,
				code: 'fiserv_commercehub',
				paymentPayload: {
					sessionId: null,
					type: null,
					threeDSecureId: undefined,
				},
				additionalData: {},
				paymentMethodName: '[name="payment[method]"',
				isIframeValid: false,
				credentials: undefined,
			},

			/**
			 * @returns {exports.initialize}
			 */
			async initialize() {
				quote.billingAddress.subscribe(function (address) {
					this.isPlaceOrderActionAllowed(address !== null);
					this.checkoutValidHandler();
				}, this);

				this.code = 'fiserv_commercehub';
				this.initializeChAdapter();

				this._super();
				this.vaultEnabler = new VaultEnabler();
				this.vaultEnabler.setPaymentCode(this.getVaultCode());

				return globalThis;
			},

			initializeChAdapter() {
				chAdapter.initialize(
					globalThis.checkoutConfig.payment[this.code],
					() => { this.iframeLoadSuccess(); },
					(valid) => { this.iframeValidHandler(valid); },
					(brand) => { this.cardBrandChangeHandler(brand); },
					(data) => { this.fieldValidityHandler(data); },
					(data) => { this.fieldFocusHandler(data); },
				);
			},

			loadSdcForm() {
				if (this.isChecked() === this.code) {
					this.loadIframe();
				}
			},

			async initCheckoutSdk(credentials) {
				await chAdapter.initSdk(
					globalThis.checkoutConfig.payment[this.code],
					credentials,
				);
			},

			loadIframe() {
				chAdapter.destroyIframe();
				this.cardBrandChangeHandler(null);

				const self = this;
				const failureCallback = this.iframeLoadFailure.bind(this);

				const iframePromise = new Promise((resolve, reject) => {
					this.beginIframeFlow();
					chAdapter.instantiateIframe(
						resolve,
						reject,
					);
				});

				iframePromise.then((data) => {}).catch((error) => {
					failureCallback(error);
				});
			},

			iframeLoadSuccess(data) {
				this.endIframeFlow();
			},

			async iframeRunSuccess(sessionId) {
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

			async run3DSecure() {
				const { transactionState, authenticationTransactionId } = await globalThis.fiserv.components.threeDSecure();

				if (transactionState.toUpperCase() === 'DECLINED') {
					throw new Error('3D-Secure authentication failure.');
				}

				this.handle3DSecureAuth(authenticationTransactionId);
			},

			handle3DSecureAuth(threeDSId) {
				this.set3DSecurePayload(threeDSId);
			},

			is3DSecureEnabled() {
				return globalThis.checkoutConfig.payment[this.code].threeDSecure === '1';
			},

			iframeLoadFailure(message) {
				this.endIframeFlow();
				this.showError(message);
			},

			iframeRunFailure(message) {
				this.showError(message ?? 'Card capture failure. Please try again.');
				this.endIframeFlow();
				this.cardBrandChangeHandler(null);
				chAdapter.resetIframe();
			},

			beginIframeFlow() {
				fullScreenLoader.startLoader();
			},

			endIframeFlow() {
				fullScreenLoader.stopLoader();
			},

			/**
			 * Set list of observable attributes
			 *
			 * @returns {exports.initObservable}
			 */
			initObservable() {
				this._super()
					.observe(['active']);

				return this;
			},

			setupPaymentMethod() {
				this.watchPaymentMethods();
				this.handleFastlaneConsent();
			},

			/**
			 * Deactivates card form when fiserv_commercehub not checked.
			 * isActive() not working with COD, for some reason.
			 */
			watchPaymentMethods() {
				$(this.paymentMethodName).on('click', (event) => {
					if (event.currentTarget.id === this.getCode()) {
						// must re-init SDK if other Fiserv APMs exist
						this.initializeChAdapter();
						this.loadIframe();
					} else {
						this.cardBrandChangeHandler(null);
						chAdapter.destroyIframe();
					}
				});
			},

			handleFastlaneConsent() {
				if (globalThis.checkoutConfig.payment.fiserv_paypal_fastlane &&
					globalThis.checkoutConfig.payment.fiserv_paypal_fastlane.consent &&
					globalThis.checkoutConfig.payment.fiserv_paypal_fastlane.consent.render &&
					$('#fiserv-paypal-consent-container').length > 0 &&
					$('fiserv-paypal-consent-container').children().length === 0) {
					globalThis.checkoutConfig.payment.fiserv_paypal_fastlane.consent.render('#fiserv-paypal-consent-container');
				}
			},

			refreshBillingAddress() {
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
			getCode() {
				return this.code;
			},

			/**
			 * @returns {Boolean}
			 */
			isVaultEnabled() {
				return this.vaultEnabler.isVaultEnabled();
			},

			/**
			 * Returns vault code.
			 *
			 * @returns {String}
			 */
			getVaultCode() {
				return globalThis.checkoutConfig.payment[this.getCode()].vaultCode;
			},

			getInvalidFieldMessages() {
				return globalThis.checkoutConfig.payment[this.getCode()].invalidFields;
			},

			/**
			 * Get BluePay Gateway Environment
			 *
			 * @returns {String}
			 */
			getEnvironment() {
				return globalThis.checkoutConfig.payment[this.getCode()].environment;
			},

			/**
			 * Get billing address
			 *
			 * @returns {String}
			 */
			getBillingAddress() {
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
			isActive() {
				const active = this.getCode() === this.isChecked();

				this.active(active);

				return active;
			},

			/**
			 * Get data
			 *
			 * @returns {Object}
			 */
			getData() {
				const data = {
					method: this.getCode(),
					additional_data: {
						payment_session: this.paymentPayload.sessionId,
					},
				};

				if (this.paymentPayload.threeDSecureId !== undefined) {
					data.additional_data['3DSecureId'] = this.paymentPayload.threeDSecureId;
				}

				data.additional_data = _.extend(data.additional_data, this.additionalData);
				this.vaultEnabler.visitAdditionalData(data);

				return data;
			},

			/**
			 * Get list of available CC types
			 *
			 * @returns {Object}
			 */
			getCcAvailableTypes() {
				return validator.getAvailableCardTypes();
			},

			/**
			 * Action to place order
			 */
			placeOrder(key) {
				const self = this;

				if (key) {
					// handle payment failure. Reinit Iframe
					$(document).one('ajaxError', (event_, xhr) => { this.placeOrderFailureHandler(event_, xhr); });

					return self._super();
				}

				return false;
			},

			placeOrderFailureHandler(event_, xhr) {
				chAdapter.destroyIframe();
				this.loadIframe();
				this.cardBrandChangeHandler(null);
				this.resetFieldValidation();
			},

			/**
			 * Trigger order placing
			 */
			placeOrderClick() {
				if (this.paymentPayload.sessionId) {
					this.placeOrder('parent');
				}
			},

			setPaymentTokenInfo(token) {
				this.setPaymentPayload(token);
			},

			validateCardType(brand) {
				let result = false;
				const mapper = pjsAdapter.getCcTypesMapper();

				if (Object.keys(mapper).length > 0) {
					const ccType = mapper[brand.toUpperCase()];

					if (ccType && this.getCcAvailableTypes().include(ccType)) {
						result = true;
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
			setPaymentPayload(sessionId) {
				this.paymentPayload.sessionId = sessionId;
			},

			set3DSecurePayload(threeDSecureId) {
				this.paymentPayload.threeDSecureId = threeDSecureId;
			},

			/**
			 * Show error message
			 *
			 * @param {String} errorMessage
			 * @private
			 */
			showError(errorMessage) {
				globalMessageList.addErrorMessage({
					message: errorMessage,
				});
			},

			enableSubmitButton() {
				this.getSubmitButton().prop('disabled', false);
			},

			disableSubmitButton() {
				this.getSubmitButton().prop('disabled', true);
			},

			getSubmitButton() {
				return $('button#fiserv-checkout-submit');
			},

			async submitIframe() {
				if (this.isPlaceOrderActionAllowed() === true && additionalValidators.validate()) {
					fullScreenLoader.startLoader();
					let credsResponse;

					try {
						credsResponse = await chSession({ threeDSecure: this.is3DSecureEnabled() });
					} catch (error) {
						console.log('An error occurred while starting Commercehub payment session: '.concat(error));
						this.iframeRunFailure();

						return;
					}
					const creds = credsResponse.ch_credentials;

					// handle 3DS
					if (this.is3DSecureEnabled()) {
						await this.initCheckoutSdk(creds);
					}

					chAdapter.submitCardForm(
						globalThis.checkoutConfig.payment.fiserv_payments.storeUrl,
						(sessionId) => { this.iframeRunSuccess(sessionId); },
						() => { this.iframeRunFailure(); },
						creds,
					);
				}
			},

			getNumberUnmaskButton() {
				return $('button#sdc-unmask-number');
			},

			getNumberMaskButton() {
				return $('button#sdc-mask-number');
			},

			getSecurityUnmaskButton() {
				return $('button#sdc-unmask-security');
			},

			getSecurityMaskButton() {
				return $('button#sdc-mask-security');
			},

			/**
			 * Checks masking mode
			 *
			 * @returns {Boolean}
			 */
			checkNumberMask() {
				const maskingMode = globalThis.checkoutConfig.payment[this.getCode()].formConfig.fields.cardNumber.masking.mode;

				if (maskingMode == 'NO_MASKING') {
					return false;
				}

				return true;
			},

			checkSecurityMask() {
				const maskingMode = globalThis.checkoutConfig.payment[this.getCode()].formConfig.fields.securityCode.masking.mode;

				if (maskingMode == 'NO_MASKING') {
					return false;
				}

				return true;
			},

			unmaskCardNumber() {
				chAdapter.unmask('cardNumber');
				const unmaskButton = this.getNumberUnmaskButton();
				const maskButton = this.getNumberMaskButton();

				unmaskButton.addClass('sdc-hidden');
				maskButton.removeClass('sdc-hidden');
			},

			maskCardNumber() {
				chAdapter.mask('cardNumber');
				const unmaskButton = this.getNumberUnmaskButton();
				const maskButton = this.getNumberMaskButton();

				unmaskButton.removeClass('sdc-hidden');
				maskButton.addClass('sdc-hidden');
			},

			unmaskSecurityCode() {
				chAdapter.unmask('securityCode');
				const unmaskButton = this.getSecurityUnmaskButton();
				const maskButton = this.getSecurityMaskButton();

				unmaskButton.addClass('sdc-hidden');
				maskButton.removeClass('sdc-hidden');
			},

			maskSecurityCode() {
				chAdapter.mask('securityCode');
				const unmaskButton = this.getSecurityUnmaskButton();
				const maskButton = this.getSecurityMaskButton();

				unmaskButton.removeClass('sdc-hidden');
				maskButton.addClass('sdc-hidden');
			},

			iframeValidHandler(valid) {
				this.isIframeValid = valid;
				this.checkoutValidHandler();
			},

			checkoutValidHandler() {
				if (this.isIframeValid === true && this.isPlaceOrderActionAllowed() === true) {
					this.enableSubmitButton();
				} else {
					this.disableSubmitButton();
				}
			},

			getCardBrandIcon() {
				return $('#sdc-card-brand-icon');
			},

			setCardBrandIconClass(cssClass) {
				const icon = this.getCardBrandIcon();

				icon.removeClass();
				icon.addClass('sdc-card-brand-icon');
				if (cssClass !== undefined) {
					icon.addClass(cssClass);
				}
			},

			cardBrandChangeHandler(brand) {
				const icon = this.getCardBrandIcon();

				switch (brand) {
					case null: {
						this.setCardBrandIconClass();
						break;
					}
					case 'visa': {
						this.setCardBrandIconClass('sdc-card-brand-icon-visa');
						break;
					}
					case 'mastercard': {
						this.setCardBrandIconClass('sdc-card-brand-icon-mastercard');
						break;
					}
					case 'american-express': {
						this.setCardBrandIconClass('sdc-card-brand-icon-amex');
						break;
					}
					case 'diners-club': {
						this.setCardBrandIconClass('sdc-card-brand-icon-diners');
						break;
					}
					case 'discover': {
						this.setCardBrandIconClass('sdc-card-brand-icon-discover');
						break;
					}
					case 'jcb': {
						this.setCardBrandIconClass('sdc-card-brand-icon-jcb');
						break;
					}
					case 'unionpay': {
						this.setCardBrandIconClass('sdc-card-brand-icon-union');
						break;
					}
					case 'maeestro': {
						this.setCardBrandIconClass('sdc-card-brand-icon-maeestro');
						break;
					}
					case 'elo': {
						this.setCardBrandIconClass('sdc-card-brand-icon-elo');
						break;
					}
				}
			},

			getSdcFieldFrame(name) {
				switch (name) {
					case 'cardNumber': {
						return $('#sdc-card-number-frame');
					}
					case 'nameOnCard': {
						return $('#sdc-card-name-frame');
					}
					case 'securityCode': {
						return $('#sdc-security-code-frame');
					}
					case 'expirationMonth': {
						return $('#sdc-exp-month-frame');
					}
					case 'expirationYear': {
						return $('#sdc-exp-year-frame');
					}
				}
			},

			getSdcFieldInvalidMessageContainer(name) {
				switch (name) {
					case 'cardNumber': {
						return $('#sdc-card-number-invalid-message');
					}
					case 'nameOnCard': {
						return $('#sdc-card-name-invalid-message');
					}
					case 'securityCode': {
						return $('#sdc-security-code-invalid-message');
					}
					case 'expirationMonth': {
						return $('#sdc-exp-month-invalid-message');
					}
					case 'expirationYear': {
						return $('#sdc-exp-year-invalid-message');
					}
				}
			},

			getSdcInvalidFieldMessageText(name) {
				switch (name) {
					case 'cardNumber': {
						return this.getInvalidFieldMessages().cardNumber;
					}
					case 'nameOnCard': {
						return this.getInvalidFieldMessages().nameOnCard;
					}
					case 'securityCode': {
						return this.getInvalidFieldMessages().securityCode;
					}
					case 'expirationMonth': {
						return this.getInvalidFieldMessages().expirationMonth;
					}
					case 'expirationYear': {
						return this.getInvalidFieldMessages().expirationYear;
					}
				}

				return '';
			},

			getSdcFields() {
				return $('.sdc-field-frame');
			},

			resetFieldValidation() {
				const fields = this.getSdcFields();

				fields.each(
					function (index) {
						$(this).removeClass('sdc-valid-field');
					},
				);
			},

			fieldValidityHandler(data) {
				const frame = this.getSdcFieldFrame(data.field);
				const mess = this.getSdcFieldInvalidMessageContainer(data.field);

				if (frame !== undefined) {
					if (data.isValid === true) {
						frame.removeClass('sdc-error-field');
						frame.addClass('sdc-valid-field');
						mess.addClass('sdc-hidden');
					} else if (data.shouldShowError === true) {
						mess.text(this.getSdcInvalidFieldMessageText(data.field));
						frame.removeClass('sdc-valid-field');
						frame.addClass('sdc-error-field');
						mess.removeClass('sdc-hidden');
					} else {
						frame.removeClass('sdc-valid-field');
						frame.removeClass('sdc-error-field');
						mess.addClass('sdc-hidden');
					}
				}
			},

			fieldFocusHandler(data) {
				const frame = this.getSdcFieldFrame(data);

				if (frame !== undefined) {
					if (frame[0].contains(document.activeElement) === true) {
						frame.addClass('sdc-focused-field');
					} else {
						frame.removeClass('sdc-focused-field');
					}
				}
			},
		});
	},
);
