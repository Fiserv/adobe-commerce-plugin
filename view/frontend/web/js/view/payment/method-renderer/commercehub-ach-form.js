/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
/* eslint-disable
	max-lines-per-function,
	max-params,
	prefer-arrow-callback,
	unicorn/prefer-module,
	unicorn/no-null,
	no-invalid-this,
	complexity,
	compat/compat,
	require-await,
	@stylistic/max-statements-per-line,
	sonarjs/cognitive-complexity,
	max-lines,
	consistent-return,
	default-case
*/
/* browser:true */
define(
	[
		'underscore',
		'jquery',
		'Magento_Checkout/js/view/payment/default',
		'Fiserv_Payments/js/ch-adapter',
		'Fiserv_Payments/js/action/create-commercehub-enriched-session',
		'Magento_Checkout/js/model/quote',
		'Magento_Checkout/js/checkout-data',
		'Magento_Ui/js/model/messageList',
		'Magento_Checkout/js/model/full-screen-loader',
		'Magento_Checkout/js/model/payment/additional-validators',
		'Fiserv_Payments/js/action/modify-requirejs',
		'Magento_Ui/js/modal/confirm',
		'ko',
		'mage/translate',
		'domReady!',
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
		fullScreenLoader,
		additionalValidators,
		modifyRequirejs,
		confirmation,
		ko,
		$t,
	) {
		'use strict';

		return Component.extend({
			isPlaceOrderActionAllowed: ko.observable(false),
			defaults: {
				template: 'Fiserv_Payments/payment/commercehub/ach-form',
				active: false,
				code: 'fiserv_ach',
				paymentPayload: {
					sessionId: null,
					type: null,
				},
				additionalData: {},
				paymentMethodName: '[name="payment[method]"',
				isIframeValid: false,
				credentials: undefined,
				lastBillingAddressKey: '',
				adapterInitialized: false,
				isIframeLoading: false,
				isIframeReady: false,
				pendingIframeReload: false,
				refreshAddressTimeoutId: null,
				billingSyncTimeoutId: null,
				billingFormObserverAttached: false,
				achRoutingNumberKey: 'routingNumber',
				achAccountNumberKey: 'accountNumber',
			},

			/**
			 * @returns {exports.initialize}
			 */
			initialize() {
				this._super();

				this.code = 'fiserv_ach';
				this.isTemplateReady = false;

				quote.billingAddress.subscribe(function (address) {
					this.handleAddressChange(address);
				}, this);

				if (quote.shippingAddress && quote.shippingAddress.subscribe) {
					quote.shippingAddress.subscribe(function (address) {
						this.handleAddressChange(address);
					}, this);
				}

				this.lastBillingAddressKey = this.getBillingAddressKey(this.getBillingAddress());
				this.isPlaceOrderActionAllowed(this.hasAvailableBillingAddress());

				return this;
			},

			hasAvailableBillingAddress() {
				const billingAddress = this.getBillingAddress();

				if (this.isAddressDataComplete(billingAddress)) {
					return true;
				}

				return this.hasCompletedBillingForm();
			},

			isAddressDataComplete(address) {
				if (!address) {
					return false;
				}

				const streetValue = Array.isArray(address.street) ?
					address.street.join(' ').trim() :
					String(address.street || '').trim();

				return Boolean(
					String(address.firstname || '').trim() &&
					String(address.lastname || '').trim() &&
					streetValue &&
					String(address.city || '').trim() &&
					String(address.postcode || '').trim() &&
					String(address.countryId || address.country_id || '').trim(),
				);
			},

			hasCompletedBillingForm() {
				const billingContainer = $(
					'.payment-method._active .payment-method-billing-address, ' +
					'.payment-method._active .billing-address-form, ' +
					'.checkout-billing-address .billing-address-form, ' +
					'form[data-role="billing-address-form"]',
				).filter(':visible').first();

				if (billingContainer.length === 0) {
					return false;
				}

				if (billingContainer.find('.mage-error:visible').length > 0) {
					return false;
				}

				const requiredFields = billingContainer.find(
					'input[aria-required="true"], select[aria-required="true"], textarea[aria-required="true"], ' +
					'input[required], select[required], textarea[required], ' +
					'input[data-validate*="required-entry"], select[data-validate*="required-entry"], textarea[data-validate*="required-entry"]',
				).filter(':enabled:visible');

				if (requiredFields.length === 0) {
					return false;
				}

				return requiredFields.toArray().every((field) => {
					const value = $(field).val();

					if (Array.isArray(value)) {
						return value.some((item) => String(item).trim() !== '');
					}

					return String(value ?? '').trim() !== '';
				});
			},

			handleAddressChange() {
				if (this.getCode() !== this.isChecked()) {
					return;
				}

				this.isPlaceOrderActionAllowed(this.hasAvailableBillingAddress());
				this.checkoutValidHandler();

				const currentKey = this.getBillingAddressKey(this.getBillingAddress());

				if (currentKey && currentKey !== this.lastBillingAddressKey) {
					this.lastBillingAddressKey = currentKey;
					this.refreshAchIframe();
				}
			},

			getBillingAddressKey(address) {
				if (!address) {
					return '';
				}

				const street = Array.isArray(address.street) ? address.street.join(' ') : (address.street || '');

				return [
					address.firstname,
					address.lastname,
					street,
					address.city,
					address.region_id,
					address.region,
					address.postcode,
					address.countryId || address.country_id,
					address.telephone,
				].filter(Boolean).join('|');
			},

			refreshAchIframe() {
				if (this.getCode() !== this.isChecked() || !this.hasAvailableBillingAddress()) {
					return;
				}

				this.isIframeValid = false;
				this.checkoutValidHandler();
				this.scheduleIframeReload();
			},

			scheduleIframeReload() {
				if (this.refreshAddressTimeoutId) {
					globalThis.clearTimeout(this.refreshAddressTimeoutId);
				}

				this.refreshAddressTimeoutId = globalThis.setTimeout(() => {
					this.refreshAddressTimeoutId = null;
					this.loadIframe(true);
				}, 500);
			},

			initializeChAdapter() {
				const normalizedConfig = this.normalizeAchConfig(this.getPaymentConfig());
				const fieldValueChangeCallback = (typeof this.fieldValueChangeHandler === 'function') ?
					(data) => { this.fieldValueChangeHandler(data); } :
					null;

				chAdapter.initialize(
					normalizedConfig,
					() => { this.iframeLoadSuccess(); },
					(valid) => { this.iframeValidHandler(valid); },
					null,
					(data) => { this.fieldValidityHandler(data); },
					(data) => { this.fieldFocusHandler(data); },
					fieldValueChangeCallback,
				);
			},

			normalizeAchConfig(config) {
				if (!config || !config.formConfig) {
					return config;
				}

				const { formConfig } = config;
				const fields = formConfig.fields || {};

				if (!fields.checkType && fields.check_type) {
					fields.checkType = fields.check_type;
				}
				if (!fields.accountType && fields.account_type) {
					fields.accountType = fields.account_type;
				}
				if (!fields.driverLicenseState && fields.driver_license_state) {
					fields.driverLicenseState = fields.driver_license_state;
				}
				if (!fields.idType && fields.id_type) {
					fields.idType = fields.id_type;
				}
				if (!fields.idValue && fields.id_value) {
					fields.idValue = fields.id_value;
				}

				this.ensureFieldParent(fields, 'accountNumber', 'fiserv_ach-account-number');
				this.ensureFieldParent(fields, 'routingNumber', 'fiserv_ach-routing-number');
				this.ensureFieldParent(fields, 'idType', 'fiserv_ach-id-type');
				this.ensureFieldParent(fields, 'idValue', 'fiserv_ach-id-value');
				this.ensureFieldParent(fields, 'driverLicenseState', 'fiserv_ach-driver-license-state');
				this.ensureFieldParent(fields, 'checkType', 'fiserv_ach-check-type');
				this.ensureFieldParent(fields, 'accountType', 'fiserv_ach-account-type');
				this.ensureFieldParent(fields, 'businessName', 'fiserv_ach-business-name');

				formConfig.fields = fields;
				config.formConfig = formConfig;

				return config;
			},

			ensureFieldParent(fields, fieldName, fallbackParentId) {
				if (!fields[fieldName]) {
					return;
				}
				const currentParentId = (fields[fieldName].parentElementId || '').toString().trim();

				if (!currentParentId) {
					fields[fieldName].parentElementId = fallbackParentId;
				}
			},

			loadSdcForm() {
				this.isTemplateReady = true;
				if (!this.adapterInitialized) {
					this.initializeChAdapter();
					this.adapterInitialized = true;
				}
				if (this.isChecked() === this.code) {
					this.loadIframe(false);
				}
			},

			areFieldContainersReady() {
				if (!this.isTemplateReady) {
					return false;
				}
				const container = document.querySelector('#fiserv_ach-account-number');

				if (!container) {
					return false;
				}
				// Check the container is visible (not inside a hidden payment method)
				const rect = container.getBoundingClientRect();

				return rect.width > 0 && rect.height > 0;
			},

			loadIframe(forceReload) {
				const shouldForceReload = forceReload === true;

				if (this.getCode() !== this.isChecked()) {
					return;
				}

				if (!shouldForceReload && this.isIframeReady && this.getCode() === this.isChecked()) {
					return;
				}

				// Don't attempt to load if DOM containers aren't ready yet
				if (!this.areFieldContainersReady()) {
					return;
				}

				if (this.isIframeLoading) {
					if (shouldForceReload) {
						this.pendingIframeReload = true;
					}

					return;
				}

				// Cancel any pending address-change reload timer
				if (this.refreshAddressTimeoutId) {
					globalThis.clearTimeout(this.refreshAddressTimeoutId);
					this.refreshAddressTimeoutId = null;
				}

				this.isIframeLoading = true;
				this.isIframeReady = false;
				this.pendingIframeReload = false;
				this.destroyAchIframe(false);

				const billingAddress = this.buildBillingAddressPayload();
				const failureCallback = this.iframeLoadFailure.bind(this);

				const iframePromise = new Promise((resolve, reject) => {
					this.beginIframeFlow();
					chAdapter.instantiateIframe(
						resolve,
						reject,
						undefined,
						billingAddress,
					);
				});

				iframePromise.catch((error) => {
					failureCallback(error);
				});
			},

			iframeLoadSuccess() {
				this.isIframeLoading = false;
				this.isIframeReady = true;
				this.endIframeFlow();

				if (this.pendingIframeReload) {
					this.pendingIframeReload = false;
					// Delay pending reload to let the SDK finish rendering iframes
					// and avoid stacking with the just-created ones
					globalThis.setTimeout(() => {
						this.loadIframe(true);
					}, 300);
				}
			},

			async iframeRunSuccess(sessionId) {
				this.setPaymentPayload(sessionId);
				this.endIframeFlow();
				this.placeOrderClick();
			},

			iframeLoadFailure(message) {
				this.isIframeLoading = false;
				this.isIframeReady = false;
				this.endIframeFlow();
				this.showError(message);

				if (this.pendingIframeReload) {
					this.pendingIframeReload = false;
					this.loadIframe(true);
				}
			},

			iframeRunFailure(message) {
				this.showError(message ?? 'ACH payment capture failure. Please try again.');
				this.endIframeFlow();
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
				this.observeBillingFormInteractions();
			},

			observeBillingFormInteractions() {
				if (this.billingFormObserverAttached) {
					return;
				}

				this.billingFormObserverAttached = true;
				$(document).off(
					'input.fiservAchBilling change.fiservAchBilling',
					'.payment-method-billing-address :input',
				);
				$(document).on(
					'input.fiservAchBilling change.fiservAchBilling',
					'.payment-method-billing-address :input',
					() => { this.scheduleBillingStateSync(); },
				);
			},

			scheduleBillingStateSync() {
				if (this.getCode() !== this.isChecked()) {
					return;
				}

				if (this.billingSyncTimeoutId) {
					globalThis.clearTimeout(this.billingSyncTimeoutId);
				}

				this.billingSyncTimeoutId = globalThis.setTimeout(() => {
					this.billingSyncTimeoutId = null;
					this.checkoutValidHandler();

					const currentKey = this.getBillingAddressKey(this.getBillingAddress());

					if (currentKey && currentKey !== this.lastBillingAddressKey) {
						this.lastBillingAddressKey = currentKey;
						this.refreshAchIframe();
					}
				}, 80);
			},

			/**
			 * Re-init SDK when switching payment methods.
			 */
			watchPaymentMethods() {
				$(this.paymentMethodName).off('click.fiservAchMethod');
				$(this.paymentMethodName).on('click.fiservAchMethod', (event) => {
					if (event.currentTarget.id === this.getCode()) {
						this.initializeChAdapter();
						this.adapterInitialized = true;
						this.loadIframe(false);
					} else {
						this.destroyAchIframe(true);
					}
				});
			},

			destroyAchIframe(resetFlags) {
				chAdapter.destroyIframe();
				this.isIframeValid = false;
				this.disableSubmitButton();
				this.clearAchFieldContainers();

				if (resetFlags === true) {
					this.isIframeLoading = false;
					this.isIframeReady = false;
					this.pendingIframeReload = false;
				}
			},

			canSubmitIframe() {
				const isBillingReady = this.hasAvailableBillingAddress();

				return this.isIframeValid === true && this.isIframeReady === true && isBillingReady === true;
			},

			clearAchFieldContainers() {
				for (const selector of [
					'#fiserv_ach-account-type',
					'#fiserv_ach-check-type',
					'#fiserv_ach-business-name',
					'#fiserv_ach-account-number',
					'#fiserv_ach-routing-number',
					'#fiserv_ach-id-type',
					'#fiserv_ach-driver-license-state',
					'#fiserv_ach-id-value',
				]) {
					const fieldContainer = $(selector);

					if (fieldContainer.length > 0) {
						fieldContainer.empty();
					}
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

			getPaymentConfig() {
				const paymentConfig = globalThis.checkoutConfig && globalThis.checkoutConfig.payment ?
					globalThis.checkoutConfig.payment : {};
				const baseCommerceHubConfig = paymentConfig.fiserv_commercehub || {};
				const sharedPaymentsConfig = paymentConfig.fiserv_payments || {};
				const achConfig = paymentConfig[this.getCode()] || {};

				return {
					...baseCommerceHubConfig,
					...sharedPaymentsConfig,
					...achConfig,
					formConfig: achConfig.formConfig || baseCommerceHubConfig.formConfig || sharedPaymentsConfig.formConfig,
				};
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
			 * Get data - same structure as card, passes sessionId
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

				data.additional_data = _.extend(data.additional_data, this.additionalData);

				return data;
			},

			/**
			 * Action to place order
			 */
			placeOrder(key) {
				if (key) {
					$(document).one('ajaxError', () => { this.placeOrderFailureHandler(); });

					return this._super();
				}

				return false;
			},

			placeOrderFailureHandler() {
				this.destroyAchIframe(true);
				this.isIframeReady = false;
				this.loadIframe(true);
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

			/**
			 * Sets payment sessionId
			 */
			setPaymentPayload(sessionId) {
				this.paymentPayload.sessionId = sessionId;
			},

			persistAchConsentAcceptance(legalText) {
				this.additionalData.ach_legal_text_accepted = 1;
				this.additionalData.ach_legal_text = legalText || '';
				this.additionalData.ach_legal_text_accepted_at = new Date().toISOString();
			},

			showAchConsentModal(legalText) {
				return new Promise((resolve) => {
					let isResolved = false;

					const done = (value) => {
						if (!isResolved) {
							isResolved = true;
							resolve(value);
						}
					};

					confirmation({
						title: $t('ACH Payment Authorization'),
						content: legalText || $t('Please review and confirm ACH authorization to continue.'),
						actions: {
							confirm() { done(true); },
							cancel() { done(false); },
							always() { done(false); },
						},
					});
				});
			},

			/**
			 * Show error message
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
				return $('button#fiserv-ach-checkout-submit');
			},

			async submitIframe() {
				const isBillingReady = this.hasAvailableBillingAddress();

				this.isPlaceOrderActionAllowed(isBillingReady);

				if (this.canSubmitIframe() !== true) {
					this.disableSubmitButton();

					return;
				}

				if (additionalValidators.validate()) {
					fullScreenLoader.startLoader();
					let credsResponse;

					const billingAddress = this.buildBillingAddressPayload();
					const sessionPayload = { billingAddress };

					try {
						credsResponse = await chSession(sessionPayload);
					} catch {
						this.iframeRunFailure();

						return;
					}

					let legalText = '';

					try {
						const legalTextResponse = await chAdapter.getAchLegalText();

						if (legalTextResponse && typeof legalTextResponse === 'object') {
							legalText = legalTextResponse.plainText ||
								legalTextResponse.achConsentText ||
								legalTextResponse.legalText ||
								legalTextResponse.text ||
								legalTextResponse.htmlText ||
								'';
						} else if (typeof legalTextResponse === 'string') {
							legalText = legalTextResponse;
						}
					} catch (error) {
						console.warn('ACH legal text retrieval failed, using default consent text.', error);
					}

					this.endIframeFlow();

					try {
						const accepted = await this.showAchConsentModal(legalText);

						if (accepted !== true) {
							return;
						}

						this.persistAchConsentAcceptance(legalText);
					} catch (error) {
						this.iframeRunFailure(error && error.message ? error.message : undefined);

						return;
					}

					const creds = credsResponse.ch_credentials;

					fullScreenLoader.startLoader();

					chAdapter.submitAchForm(
						globalThis.checkoutConfig.payment.fiserv_payments.storeUrl,
						(sessionId) => { this.iframeRunSuccess(sessionId); },
						() => { this.iframeRunFailure(); },
						creds,
					);
				}
			},

			/**
			 * Build billing address payload for ACH submission
			 * Format required by Fiserv SDK for T&C generation
			 *
			 * @returns {Object} Billing address with first/last name and address details
			 */
			buildBillingAddressPayload() {
				const billingAddress = this.getBillingAddress();

				if (!billingAddress) {
					return {};
				}

				const streetValue = Array.isArray(billingAddress.street) ?
					billingAddress.street.filter(Boolean).join(' ').trim() :
					String(billingAddress.street || '').trim();
				const stateOrProvince = String(
					billingAddress.regionCode ||
					billingAddress.region ||
					billingAddress.region_id ||
					'',
				).trim();
				const country = String(billingAddress.countryId || billingAddress.country_id || '').trim();

				return {
					firstName: String(billingAddress.firstname || '').trim(),
					lastName: String(billingAddress.lastname || '').trim(),
					address: {
						street: streetValue,
						city: String(billingAddress.city || '').trim(),
						stateOrProvince,
						postalCode: String(billingAddress.postcode || '').trim(),
						country,
					},
				};
			},

			getBillingAddress() {
				let billingAddress = quote.billingAddress();

				if (!billingAddress) {
					billingAddress = checkoutData.getBillingAddressFromData();
				}

				if (!this.isAddressDataComplete(billingAddress)) {
					const billingAddressFromDom = this.getBillingAddressFromForm();

					if (this.isAddressDataComplete(billingAddressFromDom)) {
						billingAddress = billingAddressFromDom;
					}
				}

				return billingAddress;
			},

			getBillingAddressFromForm() {
				const getFieldValue = (selectors) => {
					for (const selector of selectors) {
						const element = $(selector).filter(':visible').first();

						if (element.length) {
							const value = element.val();

							if (Array.isArray(value)) {
								const firstNonEmpty = value.find((item) => String(item || '').trim() !== '');

								if (firstNonEmpty !== undefined) {
									return String(firstNonEmpty).trim();
								}
							} else if (String(value || '').trim() !== '') {
								return String(value).trim();
							}
						}
					}

					return '';
				};

				const streetLine1 = getFieldValue([
					'input[name="street[0]"]',
					'input[name$="[street][0]"]',
				]);
				const streetLine2 = getFieldValue([
					'input[name="street[1]"]',
					'input[name$="[street][1]"]',
				]);

				return {
					firstname: getFieldValue(['input[name="firstname"]', 'input[name$="[firstname]"]']),
					lastname: getFieldValue(['input[name="lastname"]', 'input[name$="[lastname]"]']),
					street: [streetLine1, streetLine2].filter(Boolean),
					city: getFieldValue(['input[name="city"]', 'input[name$="[city]"]']),
					postcode: getFieldValue(['input[name="postcode"]', 'input[name$="[postcode]"]']),
					countryId: getFieldValue(['select[name="country_id"]', 'select[name$="[country_id]"]']),
					regionCode: getFieldValue(['select[name="region_id"]', 'select[name$="[region_id]"]', 'input[name="region"]', 'input[name$="[region]"]']),
				};
			},

			/**
			 * Masking controls for account number
			 */
			checkAccountNumberMask() {
				const { formConfig } = this.getPaymentConfig();

				if (formConfig && formConfig.fields && formConfig.fields.accountNumber) {
					const maskingMode = formConfig.fields.accountNumber.masking.mode;

					return maskingMode !== 'NO_MASKING';
				}

				return false;
			},

			unmaskAccountNumber() {
				chAdapter.unmask('accountNumber');
				$('#sdc-unmask-account').addClass('sdc-hidden');
				$('#sdc-mask-account').removeClass('sdc-hidden');
			},

			maskAccountNumber() {
				chAdapter.mask('accountNumber');
				$('#sdc-unmask-account').removeClass('sdc-hidden');
				$('#sdc-mask-account').addClass('sdc-hidden');
			},

			checkRoutingNumberMask() {
				const { formConfig } = this.getPaymentConfig();

				if (formConfig && formConfig.fields && formConfig.fields.routingNumber) {
					const maskingMode = formConfig.fields.routingNumber.masking.mode;

					return maskingMode !== 'NO_MASKING';
				}

				return false;
			},

			unmaskRoutingNumber() {
				chAdapter.unmask('routingNumber');
				$('#sdc-unmask-routing').addClass('sdc-hidden');
				$('#sdc-mask-routing').removeClass('sdc-hidden');
			},

			maskRoutingNumber() {
				chAdapter.mask('routingNumber');
				$('#sdc-unmask-routing').removeClass('sdc-hidden');
				$('#sdc-mask-routing').addClass('sdc-hidden');
			},

			iframeValidHandler(valid) {
				this.isIframeValid = valid;
				this.checkoutValidHandler();
			},

			checkoutValidHandler() {
				const isBillingReady = this.hasAvailableBillingAddress();

				this.isPlaceOrderActionAllowed(isBillingReady);

				if (this.canSubmitIframe() === true) {
					this.enableSubmitButton();
				} else {
					this.disableSubmitButton();
				}
			},

			getSdcFieldFrame(name) {
				switch (name) {
					case 'accountNumber': {
						return $('#sdc-account-number-frame');
					}
					case 'routingNumber': {
						return $('#sdc-routing-number-frame');
					}
					case 'idType': {
						return $('#sdc-id-type-frame');
					}
					case 'idValue': {
						return $('#sdc-id-value-frame');
					}
					case 'driverLicenseState': {
						return $('#sdc-dl-state-frame');
					}
					case 'accountType': {
						return $('#sdc-account-type-frame');
					}
					case 'checkType': {
						return $('#sdc-check-type-frame');
					}
					case 'businessName': {
						return $('#sdc-business-name-frame');
					}
				}
			},

			getSdcFieldInvalidMessageContainer(name) {
				switch (name) {
					case 'accountNumber': {
						return $('#sdc-account-number-invalid-message');
					}
					case 'routingNumber': {
						return $('#sdc-routing-number-invalid-message');
					}
					case 'idType': {
						return $('#sdc-id-type-invalid-message');
					}
					case 'idValue': {
						return $('#sdc-id-value-invalid-message');
					}
					case 'driverLicenseState': {
						return $('#sdc-dl-state-invalid-message');
					}
					case 'accountType': {
						return $('#sdc-account-type-invalid-message');
					}
					case 'checkType': {
						return $('#sdc-check-type-invalid-message');
					}
					case 'businessName': {
						return $('#sdc-business-name-invalid-message');
					}
				}
			},

			getSdcInvalidFieldMessageText(fieldName) {
				const messages = {
					accountNumber: 'Invalid Account Number',
					routingNumber: 'Invalid Routing Number',
					idType: 'Invalid Identification Type',
					idValue: 'Invalid Identification Value',
					driverLicenseState: 'Invalid Driver License State',
					accountType: 'Invalid Account Type',
					checkType: 'Invalid Check Type',
					businessName: 'Invalid Business Name',
				};

				return messages[fieldName] || 'Invalid field value';
			},

			getSdcFields() {
				return $('.sdc-field-frame');
			},

			resetFieldValidation() {
				const fields = this.getSdcFields();

				fields.each((index, fieldElement) => {
					$(fieldElement).removeClass('sdc-valid-field');
				});
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

			normalizeAchFieldName(fieldName) {
				const normalized = String(fieldName || '').trim();

				if (normalized === 'routing_number' || normalized === 'routingNumber') {
					return 'routingNumber';
				}

				if (normalized === 'account_number' || normalized === 'accountNumber') {
					return 'accountNumber';
				}

				return '';
			},

			maskAchAccountNumber(accountNumber) {
				const digitsOnly = String(accountNumber || '').replaceAll(/\D/g, '');

				if (!digitsOnly) {
					return '';
				}

				const last4 = digitsOnly.slice(-4);

				return `XXXXX${last4}`;
			},

			setAchFieldAdditionalData(fieldName, fieldValue) {
				const normalizedField = this.normalizeAchFieldName(fieldName);
				const trimmedValue = String(fieldValue || '').trim();

				if (!normalizedField || !trimmedValue) {
					return;
				}

				if (normalizedField === 'routingNumber') {
					this.additionalData[this.achRoutingNumberKey] = trimmedValue;
					this.additionalData.routing_number = trimmedValue;

					return;
				}

				this.additionalData[this.achAccountNumberKey] = this.maskAchAccountNumber(trimmedValue);
				this.additionalData.account_number = this.additionalData[this.achAccountNumberKey];
			},

			fieldValueChangeHandler(data) {
				if (data && typeof data === 'object') {
					if (data.values && typeof data.values === 'object') {
						for (const fieldName of Object.keys(data.values)) {
							this.setAchFieldAdditionalData(fieldName, data.values[fieldName]);
						}
					} else {
						this.setAchFieldAdditionalData(data.field || data.name, data.value);
						this.setAchFieldAdditionalData('routingNumber', data.routingNumber);
						this.setAchFieldAdditionalData('accountNumber', data.accountNumber);
					}
				}

				this.checkoutValidHandler();
			},
		});
	},
);
