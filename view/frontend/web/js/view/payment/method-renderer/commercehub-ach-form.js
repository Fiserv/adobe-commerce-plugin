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
                addressComponent: undefined,
			paymentMethodName: '[name="payment[method]"',
                isIframeValid: false,
                lastBillingAddressKey: '',
                adapterInitialized: false,
                isIframeReady: false,
                pendingIframeReload: false,
                refreshAddressTimeoutId: null,
                billingSyncTimeoutId: null,
                billingFormObserverAttached: false,
                achRoutingNumberKey: 'routingNumber',
                achAccountNumberKey: 'accountNumber',
                billingAddressContainerId: 'fiserv-ach-billing-address-container',
                paymentSessionData: undefined
            },

            /**
             * @returns {exports.initialize}
             */
            initialize() {
                this._super();
                this.code = 'fiserv_ach';

                quote.billingAddress.subscribe(function (address) {
                    this.isPlaceOrderActionAllowed(address !== null);
                    this.checkoutValidHandler();
                }, this);

                return this;
            },

            hasAvailableBillingAddress() {
                const billingAddress = this.getBillingAddress();
                if (this.isAddressDataComplete(billingAddress)) return true;

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

            initializeChAdapter() {
				chAdapter.initialize(
					this.getPaymentConfig(),
					() => { this.iframeLoadSuccess(); },
					(valid) => { this.iframeValidHandler(valid); },
					null,
					(data) => { this.fieldValidityHandler(data); },
					(data) => { this.fieldFocusHandler(data); },
					(data) => { this.fieldValueChangeHandler(data); },
				);
			},

            loadSdcForm() {

                if (this.isChecked() === this.code) {
                    this.loadIframe()
                        .catch((e) => {
                            this.iframeLoadFailure(e);
                        });
                }
            },

            async loadIframe() {
                this.beginIframeFlow();
			this.initializeChAdapter();

                const billingAddress = this.getBillingAddress();
                if (!billingAddress) throw new Error("Billing address not found.");

                await this.initCheckoutSdk(billingAddress);

                // Create the address component and populate it with the user's billing
                // address BEFORE passing it to instantiateIframe.  Populating first ensures
                // addressComponent.isValid() === true at the moment the SDK registers it
                // inside paymentFields (registerAddress sets addressValidity[0] once at
                // mount time).  If creation fails we fall back to undefined so
                // addressValidity[0] defaults to true and the form still works.
                this.addressComponent = await this.initSdkAddressComponent(billingAddress);
                this.populateAddressComponent();

                // NOTE: We intentionally do NOT pass { billingAddress: this.addressComponent }
                // to instantiateIframe here.  If the address component is mounted inside
                // paymentFields the SDK tracks its validity via addressValidity[0].  Any
                // field that fails the SDK's schema (stateOrProvince must be a <select> in
                // en-US; mapAddressFieldsToTempForm creates the correct element type) would
                // set that flag to false and cause throwIfInvalid() to throw
                // COMPONENT_DEPENDENCIES_NOT_SATISFIED on every getAchLegalText()/submit()
                // call.  Billing address is instead passed as an explicit argument to both
                // calls in submitIframe() so it still reaches the Fiserv API.
                chAdapter.instantiateIframe(
                    () => { this.iframeLoadSuccess(); },
                    (e) => { this.iframeLoadFailure(e); }
                );
            },

            /**
             * Create a Fiserv address component wired to hidden DOM inputs so the
             * SDK can read the billing address at submit / legal-text time.
             *
             * v4 requirement: stateOrProvince MUST be a <select> element for the
             * en-US locale.  The SDK auto-populates the <select> with US state
             * options during window.fiserv.components.address() initialisation, so
             * we only need to provide the element – populateAddressComponent() sets
             * the correct value afterwards.
             */
            async initSdkAddressComponent(billingAddress) {
                try {
                    const container = this.getBillingAddressContainer();
                    const builtAddress = this.buildBillingAddressPayload(billingAddress);
                    const fieldMap = this.mapBillingAddressFields(builtAddress);
                    const mappedFields = this.mapAddressFieldsToTempForm(container, fieldMap);
                    return await chAdapter.createBillingAddressComponent(mappedFields);
                } catch (e) {
                    console.warn('[ACH] Billing address component could not be created, proceeding without it:', e);
                    return undefined;
                }
            },

            populateAddressComponent() {
                if (!this.addressComponent || typeof this.addressComponent !== 'object') return;

                const billingAddress = this.getBillingAddress();
                if (!billingAddress) return;

                const mappedAddress = this.mapBillingAddressFields(this.buildBillingAddressPayload(billingAddress));

                // populate() calls setValue() on each field's formatter then re-runs
                // revalidate() so addressComponent.isValid() reflects the new data.
                this.addressComponent.populate({ firstName: mappedAddress.firstName });
                this.addressComponent.populate({ lastName: mappedAddress.lastName });
                this.addressComponent.populate({ street: mappedAddress.street });
                this.addressComponent.populate({ city: mappedAddress.city });
                this.addressComponent.populate({ stateOrProvince: mappedAddress.stateOrProvince });
                this.addressComponent.populate({ postalCode: mappedAddress.postalCode });
                this.addressComponent.populate({ country: mappedAddress.country });
            },

            iframeLoadSuccess() {
                this.endIframeFlow();
            },

            iframeRunSuccess() {
		if (!this.paymentSessionData || !this.paymentSessionData['sessionId'])
		{
			this.iframeRunFailure('Payment session data not found.');
			return;
		}
                this.setPaymentPayload(this.paymentSessionData['sessionId']);
                this.endIframeFlow();
                this.placeOrderClick();
            },

            iframeLoadFailure(message) {
                this.isIframeReady = false;
                this.endIframeFlow();
                this.showError(message);
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
            },

            /**
             * Re-init SDK when switching payment methods.
             */
            watchPaymentMethods() {
                $(this.paymentMethodName).off('click.fiservAchMethod');
                $(this.paymentMethodName).on('click.fiservAchMethod', (event) => {
                    if (event.currentTarget.id === this.getCode()) {
                        this.initializeChAdapter();
                        this.loadIframe()
                            .catch((e) => {this.iframeLoadFailure(e);});
                    } else {
                        this.destroyAchIframe(true);
                    }
                });
            },

            destroyAchIframe(resetFlags) {
                chAdapter.destroyIframe();
                this.disableSubmitButton();
                this.clearAchFieldContainers();

                if (resetFlags === true) {
                    this.isIframeReady = false;
                    this.pendingIframeReload = false;
                }
            },

            canSubmitIframe() {
                const isBillingReady = this.hasAvailableBillingAddress();

                return this.isIframeValid === true && isBillingReady === true;
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
                    $(document).one('ajaxError', () => {this.placeOrderFailureHandler();});

                    return this._super();
                }

                return false;
            },

            placeOrderFailureHandler() {
                this.destroyAchIframe(true);
                this.loadIframe(true)
                    .catch((e) => {this.iframeLoadFailure(e);});
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
                try {
                    if (additionalValidators.validate()) {
                        fullScreenLoader.startLoader();

                        // Build billing address payload to pass to the SDK for both ACH legal text request and the form submit.
                        const rawBillingAddress = this.getBillingAddress();
                        const billingPayload = rawBillingAddress
                            ? this.buildBillingAddressPayload(rawBillingAddress)
                            : undefined;

                        const legalText = await chAdapter.getAchLegalText(billingPayload);
                        this.endIframeFlow();

                        const accepted = await this.showAchConsentModal(legalText);
                        if (accepted !== true) return;

                        this.persistAchConsentAcceptance(legalText);

                        fullScreenLoader.startLoader();

                        chAdapter.submitIframeForm(
                            () => { this.iframeRunSuccess(); },
                            (e) => { this.iframeRunFailure(e.message || 'An error occurred during ACH processing. Please try again.'); }
                        );
                    }

                } catch (error) {
                    this.iframeRunFailure(error.message || 'An error occurred during ACH processing. Please try again.');
                }
            },

            async initCheckoutSdk(billingAddress) {
                const billingPayload = this.buildBillingAddressPayload(billingAddress);
                const credsResponse = await chSession({billingAddress: billingPayload});
                this.paymentSessionData = credsResponse["ch_credentials"];

                this.contextId = crypto.randomUUID();
                await chAdapter.initSdk(
                    this.getPaymentConfig(), 
                    this.paymentSessionData, 
                    this.contextId
                );
            },

            /**
             * Build billing address payload for ACH submission
             * Format required by Fiserv SDK for T&C generation
             *
             * @returns {Object} Billing address with first/last name and address details
             */
            buildBillingAddressPayload(billingAddress) {
                if (!billingAddress) throw new Error("Billing address not found.");

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

                if (!billingAddress) billingAddress = checkoutData.getBillingAddressFromData();
                if (this.isAddressDataComplete(billingAddress)) return billingAddress

                billingAddress = this.getBillingAddressFromForm();
                if (this.isAddressDataComplete(billingAddress)) return billingAddress;

                return undefined;
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
                const {formConfig} = this.getPaymentConfig();

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
                const {formConfig} = this.getPaymentConfig();

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
                if (this.canSubmitIframe() === true) {
                    this.isPlaceOrderActionAllowed(true);
                    this.enableSubmitButton();
                } else {
                    this.isPlaceOrderActionAllowed(false);
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

            mapAddressFieldsToTempForm(container, fieldMap) {
                const addressFields = {};

                for (const key in fieldMap) {
                    const inputId = 'fiserv-ach-billing-' + key;

                    // v4 SDK: the address component schema requires stateOrProvince to be
                    // a <select> element in the en-US locale (the SDK's address component
                    // validates element type via Xr(["SELECT","FISERV-SELECT"])).
                    // The SDK also auto-populates the <select> with US state options during
                    // window.fiserv.components.address() init, so we just provide the element
                    // and let populateAddressComponent() set the value afterwards.
                    const isSelect = key === 'stateOrProvince';
                    const element = document.createElement(isSelect ? 'select' : 'input');

                    if (!isSelect) {
                        element.type = 'text';
                        element.value = fieldMap[key];
                    }

                    element.id = inputId;
                    container.append(element);
                    addressFields[key] = { elementId: inputId };
                }

                return addressFields;
            },

            getBillingAddressContainer() {
                let container = document.getElementById(this.billingAddressContainerId);

                if (!container) {
                    container = document.createElement('div');
                    container.id = this.billingAddressContainerId;
                    container.style.display = 'none';
                    document.body.append(container);
                }

                container.innerHTML = '';

                return container;
            },

            mapBillingAddressFields(billingAddress) {
                if (!billingAddress || typeof billingAddress !== 'object') throw Error("Missing billing address");

                const address = billingAddress.address || {};
                return {
                    firstName: billingAddress.firstName || '',
                    lastName: billingAddress.lastName || '',
                    street: address.street || '',
                    city: address.city || '',
                    stateOrProvince: address.stateOrProvince || '',
                    postalCode: address.postalCode || '',
                    country: address.country || '',
                };
            }
        });
    },
);
