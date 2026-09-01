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
		'Fiserv_Payments/js/action/create-commercehub-enriched-session',
		'Fiserv_Payments/js/action/verify-account',
		'Magento_Checkout/js/model/quote',
		'Magento_Checkout/js/checkout-data',
		'Magento_Ui/js/model/messageList',
		'Magento_Vault/js/view/payment/vault-enabler',
		'Magento_Checkout/js/model/full-screen-loader',
		'Magento_Checkout/js/model/payment/additional-validators',
		'Fiserv_Payments/js/action/modify-requirejs',
		'Fiserv_Payments/js/model/surcharge',
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
		verifyAccount,
		quote,
		checkoutData,
		globalMessageList,
		VaultEnabler,
		fullScreenLoader,
		additionalValidators,
		modifyRequirejs,
		surchargeModel,
		ko,
		$t
	) {
		'use strict';

		return Component.extend({
			isPlaceOrderActionAllowed: ko.observable(quote.billingAddress() != null),
			surchargeConfirmed: ko.observable(false),
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
                paymentSessionData: undefined,
                contextId: undefined
			},

		/**
		 * @returns {exports.initialize}
		 */
		initialize: async function () {
            quote.billingAddress.subscribe(function (address) {
                this.isPlaceOrderActionAllowed(address !== null);
				this.checkoutValidHandler();
                }, this);

			quote.totals.subscribe(function (newTotals) {
				if (!this.isSurchargeEnabled()) {
					return;
				}
				if (!newTotals) {
					return;
				}
				if (this._reestimatingSurcharge) {
					return;
				}
				if (surchargeModel.surchargeData() === null && !this.surchargeConfirmed()) {
					return;
				}

				const newGrandTotal = parseFloat(newTotals.grand_total) || 0;
				if (newGrandTotal <= 0) {
					// Fully covered by store credit or gift card, therefore no surcharge.
					surchargeModel.surchargeData(null);
					this.surchargeConfirmed(false);
					this.checkoutValidHandler();
					return;
				}

				// Partial coverage: there is still a balance owed on the payment card, so re-estimate the
				// surcharge against the new remaining balance
				this._reestimatingSurcharge = true;
				fullScreenLoader.startLoader();
				this.fetchSurchargeEstimate()
					.then((success) => {
						if (!success) {
							surchargeModel.surchargeData(null);
							this.surchargeConfirmed(false);
						}
						this.checkoutValidHandler();
					})
					.catch(() => {
						surchargeModel.surchargeData(null);
						this.surchargeConfirmed(false);
						this.checkoutValidHandler();
					})
					.finally(() => {
						fullScreenLoader.stopLoader();
						this._reestimatingSurcharge = false;
					});
			}, this);

			this.code = 'fiserv_commercehub';

				this._super();
				this.vaultEnabler = new VaultEnabler();
				this.vaultEnabler.setPaymentCode(this.getVaultCode());

                return this;
			},

			initializeChAdapter: function ()
			{
				chAdapter.initialize(
					window.checkoutConfig.payment[this.code],
					() => { this.iframeLoadSuccess(); },
					(valid) => { this.iframeValidHandler(valid); },
					(brand) => { this.cardBrandChangeHandler(brand); },
					(data) => { this.fieldValidityHandler(data); },
					(data) => { this.fieldFocusHandler(data); },
					(data) => { this.fieldValueChangeHandler(data); }
				);
			},

		loadSdcForm: function () {
			if (this.isChecked() === this.code) {
				this.loadIframe();
			}
		},

        initCheckoutSdk: async function(credentials)
        {
            const credsResponse = await chSession({
                threeDSecure: this.is3DSecureEnabled(),
                billingAddress: this.buildBillingAddressValues(),
                paymentCode: this.code
            });

            this.paymentSessionData = credsResponse['ch_credentials'];
            this.contextId = crypto.randomUUID();

            await chAdapter.initSdk(
                window.checkoutConfig.payment[this.code],
                this.paymentSessionData,
                this.contextId
            );
        },


        loadIframe: async function () {
            chAdapter.destroyIframe();
		this.initializeChAdapter();
            this.cardBrandChangeHandler(null);


		fullScreenLoader.startLoader();
            try {
                await this.initCheckoutSdk();
            } catch (error) {
                console.log("An error occurred while initializing Commercehub SDK: ".concat(error));
                this.iframeLoadFailure();
                return;
            }

            this.beginIframeFlow();
            const addressValues = this.buildBillingAddressValues();

            try {
                await chAdapter.instantiateIframe(
                    () => { this.iframeLoadSuccess(); },
                    (error) => { this.iframeLoadFailure(error); },
                    { data: { billingAddress: addressValues } },
                )
            } catch (error) {
                console.log("An error occurred while loading Commercehub iframe: ".concat(error));
                this.iframeLoadFailure();
                return;
            }

	},

		/** Builds a nested billing address object required by the surcharge estimates API */
		buildEstimateBillingAddress: function() {
			const billingAddress = quote.billingAddress();
			return {
				firstName: billingAddress.firstname,
				lastName: billingAddress.lastname,
				address: {
					street: billingAddress.street[0],
					houseNumberOrName: billingAddress.street[1],
					city: billingAddress.city,
					stateOrProvince: billingAddress.regionCode || billingAddress.region,
					postalCode: billingAddress.postcode,
					country: billingAddress.countryId
				}
			};
		},

        /**
         * Builds a static billing address values object from the current quote for the CH SDK address component.
         */
		buildBillingAddressValues: function() {
			const billingAddress = quote.billingAddress();
			if (!billingAddress) {
					return undefined;
				}
				return {
					firstName: billingAddress.firstname || '',
					lastName: billingAddress.lastname || '',
					street: (billingAddress.street && billingAddress.street[0]) || '',
					houseNumberOrName: (billingAddress.street && billingAddress.street[1]) || '',
					city: billingAddress.city || '',
					stateOrProvince: billingAddress.regionCode || '',
					postalCode: billingAddress.postcode || '',
					country: billingAddress.countryId || ''
				};
			},

			iframeLoadSuccess: function(data) {
				this.endIframeFlow();
			},

			iframeRunSuccess: async function () {
                if (!this.paymentSessionData || !this.paymentSessionData["sessionId"]) {
                    this.iframeRunFailure($t("Payment session not established. Please try again."));
                    this.endIframeFlow();
                    return;
                }

                if (!this.contextId) {
                    this.iframeRunFailure($t("Context ID not established. Please try again."));
                    this.endIframeFlow();
                    return;
                }   

				this.setPaymentPayload(this.paymentSessionData["sessionId"]);

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

			/**
			 * Perform account verification as a discrete step: obtain a session, capture the card, & verify the account.
			 * This is used when account verification config option is enabled.
			 * @return {Promise}
			 */
			performAccountVerification: async function () {
				await chAdapter.submitIframeForm(
					async () => { await verifyAccount({ 'sessionId' : this.paymentSessionData['sessionId'] }); },
					() => { this.showError($t('Card capture failed during account verification. Please try again.')); }
				);
			},

		run3DSecure: async function () {
			const threeDSConfig = chAdapter.contextId ? { context: window.fiserv.context(chAdapter.contextId) } : undefined;
			const {transactionState, authenticationTransactionId} = await window.fiserv.components.threeDSecure(threeDSConfig);
			if (transactionState.toUpperCase() === "DECLINED") {
					throw new Error($t('3D-Secure authentication failure.'));
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
				this.showError(message ?? $t('Card capture failure. Please try again.'));
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

			setupPaymentMethod: function() {
				this.watchPaymentMethods();
				this.handleFastlaneConsent();
			},

			/**
			 * Deactivates card form when fiserv_commercehub not checked.
			 * isActive() not working with COD, for some reason.
			 */
			watchPaymentMethods: function () {
				$(this.paymentMethodName).on("click", (event) => {
					if (event.currentTarget.id === this.getCode()) {
						// must re-init SDK if other Fiserv APMs exist
						this.loadIframe();
					} else {
						this.cardBrandChangeHandler(null);
						chAdapter.destroyIframe();
						this.clearSurcharge();
						this.surchargeConfirmed(false);
					}
				});
			},

			handleFastlaneConsent: function() {
				if (window.checkoutConfig.payment.fiserv_paypal_fastlane &&
					window.checkoutConfig.payment.fiserv_paypal_fastlane.consent &&
					window.checkoutConfig.payment.fiserv_paypal_fastlane.consent.render &&
					$("#fiserv-paypal-consent-container").length &&
					!$("fiserv-paypal-consent-container").children().length)
				{
					window.checkoutConfig.payment.fiserv_paypal_fastlane.consent.render("#fiserv-paypal-consent-container");
				}
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
			const data = {
				'method': this.getCode(),
				'additional_data': {
					'payment_session': this.paymentPayload.sessionId
				}
			};

			if (typeof(this.paymentPayload.threeDSecureId) !== "undefined") {
				data['additional_data']['3DSecureId'] = this.paymentPayload.threeDSecureId;
			}

			const surchargeData = surchargeModel.surchargeData();
			if (surchargeData && surchargeData.applied && surchargeData.amount > 0) {
				data['additional_data']['surcharge_estimate'] = surchargeData.amount;
				if (surchargeData.baseAmount > 0) {
					data['additional_data']['surcharge_base_amount'] = surchargeData.baseAmount;
				}
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
			const self = this;

			if (key) {
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

					// Perform account verification as a discrete step before the main card capture
					if (window.checkoutConfig.payment[this.code]['accountVerificationEnabled']) {
						try {
							await this.performAccountVerification();
						} catch (error) {
							this.showError(error.message || $t('Account verification failed. Please try again.'));
							this.endIframeFlow();
							return;
						}
					}

					await chAdapter.submitIframeForm(
						() => { this.iframeRunSuccess(); },
						() => { this.iframeRunFailure(); }
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
				if (!valid) {
					this.resetSurchargeEstimateState();
				}
				this.checkoutValidHandler();
			},

			checkoutValidHandler: function() {
				if (this.isIframeValid === true && this.isPlaceOrderActionAllowed() === true) {
					if (this.isSurchargeEnabled() && !this.surchargeConfirmed()) {
						this.enableContinueButton();
						this.disableSubmitButton();
					} else {
						this.disableContinueButton();
						this.enableSubmitButton();
					}
				} else {
					this.disableSubmitButton();
				}
			},

			isSurchargeEnabled: function() {
				return !!window.checkoutConfig.payment[this.code]["surchargeEnabled"];
			},

	fetchSurchargeEstimate: async function() {
		try {
			if (!this.paymentSessionData || !this.paymentSessionData['sessionId']) {
				surchargeModel.surchargeData(null);
				return false;
			}
			const surchargeDetails = await chAdapter.getSurchargeEstimate();
				const surchargeAmount = (surchargeDetails && surchargeDetails.amountComponent
					&& surchargeDetails.amountComponent.surcharge)
					? parseFloat(surchargeDetails.amountComponent.surcharge)
					: 0;

				if (surchargeAmount > 0) {
					const surchargeBaseAmount = (surchargeDetails.amount && surchargeDetails.amount.total)
						? parseFloat(surchargeDetails.amount.total) - surchargeAmount
						: 0;

					const currentQuoteTotal = quote.totals() ? (parseFloat(quote.totals().grand_total) || 0) : 0;
					const displayGrandTotal = Math.round((currentQuoteTotal + surchargeAmount) * 100) / 100;

					surchargeModel.surchargeData({
						applied: true,
						disclosureText: (surchargeDetails.disclosureText && surchargeDetails.disclosureText.length)
							? surchargeDetails.disclosureText[0].value
							: '',
						amount: surchargeAmount,
						baseAmount: surchargeBaseAmount,
						grandTotal: displayGrandTotal,
						currency: (surchargeDetails.amount && surchargeDetails.amount.currency) || 'USD'
					});
				} else {
					surchargeModel.surchargeData(null);
				}
				return true;
			} catch (e) {
				surchargeModel.surchargeData(null);
				return false;
			}
		},

		handleContinue: async function() {
			if (!this.isIframeValid || !this.isPlaceOrderActionAllowed()) {
				return;
			}
			this.disableContinueButton();
			fullScreenLoader.startLoader();
			try {
				if (this.isSurchargeEnabled()) {
					const success = await this.fetchSurchargeEstimate();
					if (success) {
						this.surchargeConfirmed(true);
						this.enableSubmitButton();
					} else {
						this.showError($t('Unable to retrieve surcharge estimate. Please try again.'));
						this.enableContinueButton();
					}
				} else {
					this.surchargeConfirmed(true);
					this.enableSubmitButton();
				}
			} catch (e) {
				this.showError($t('Unable to retrieve surcharge estimate. Please try again.'));
				this.enableContinueButton();
			} finally {
				fullScreenLoader.stopLoader();
			}
		},

			clearSurcharge: function() {
				surchargeModel.surchargeData(null);
			},

			resetSurchargeEstimateState: function() {
				if (!this.isSurchargeEnabled() || !this.surchargeConfirmed()) {
					return;
				}

				this.clearSurcharge();
				this.surchargeConfirmed(false);
				this.checkoutValidHandler();
			},


			enableContinueButton: function() {
				this.getContinueButton().prop('disabled', false);
			},

			disableContinueButton: function() {
				this.getContinueButton().prop('disabled', true);
			},

			getContinueButton: function() {
				return $('button#fiserv-checkout-continue');
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


				if (this._lastCardBrand && brand && this._lastCardBrand !== brand) {
					this.resetSurchargeEstimateState();
				}
				this._lastCardBrand = brand || null;

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

				// Reset surcharge confirmation so the user must click Continue for a new estimate.
				if (data && data["field"] === "cardNumber") {
					this.resetSurchargeEstimateState();
				}

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

						if (data === 'cardNumber') {
							this.resetSurchargeEstimateState();
						}
					}
				}
			},
			fieldValueChangeHandler: function(data) {
				this.resetSurchargeEstimateState();
			}
		});
	}
);
