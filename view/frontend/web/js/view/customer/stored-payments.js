define([
	'ko',
	'uiComponent',
	'Fiserv_Payments/js/ch-adapter',
	'Magento_Checkout/js/model/full-screen-loader',
	'Magento_Ui/js/model/messageList',
	'jquery',
	'domReady!',
], (
	ko,
	Component,
	chIframe,
	fullScreenLoader,
	globalMessageList,
	$,
) => {
	'use strict';

	return Component.extend({
		defaults: {
			tokenConfig: {},
			tokenizeUrl: 'fiserv/vault/tokenizesession',
			tokenizeErrorMsg: 'Something went wrong. Please try again later.',
		},

		/**
			 * @returns {exports.initialize}
			 */
		initialize(config) {
			if (config.tokenConfig === undefined) {
				throw new Error('Required parameter tokenConfig not found!');
			}

			const self = this;

			this.tokenConfig = config.tokenConfig;
			chIframe.initialize(
				this.tokenConfig,
				this.iframeLoadSuccess.bind(this),
				this.iframeValidHandler.bind(this),
				(brand) => { this.cardBrandChangeHandler(brand); },
				(data) => { this.fieldValidityHandler(data); },
				(data) => { this.fieldFocusHandler(data); },
			);

			this.getFormContainer().hide();
			this.showTokenizationContainer();

			self._super();

			return self;
		},

		getFormContainer() {
			return $('#sdc-container');
		},

		showTokenizationContainer() {
			this.getTokenizationContainer().show();
			this.disableCreateButton();
		},

		getTokenizationContainer() {
			return $('#tokenization-builder-container');
		},

		hideAddButton() {
			this.getAddButton().hide();
		},

		showAddButton() {
			this.getAddButton().show();
		},

		getAddButton() {
			return $('button#tokenization-builder-add');
		},

		hideIframeButtons() {
			this.getIframeButtons().hide();
			this.disableIframeButtons();
		},

		showIframeButtons() {
			this.getIframeButtons().show();
			this.enableIframeButtons();
		},

		enableIframeButtons() {
			this.enableCancelButton();
			this.enableCreateButton();
		},

		disableIframeButtons() {
			this.disableCancelButton();
			this.disableCreateButton();
		},

		getIframeButtons() {
			return $('div#tokenization-iframe-buttons');
		},

		enableCreateButton() {
			this.getCreateButton().prop('disabled', false);
		},

		disableCreateButton() {
			this.getCreateButton().prop('disabled', true);
		},

		getCreateButton() {
			return $('button#tokenization-builder-create');
		},

		enableCancelButton() {
			this.getCancelButton().prop('disabled', false);
		},

		disableCancelButton() {
			this.getCancelButton().prop('disabled', true);
		},

		getCancelButton() {
			return $('button#tokenization-builder-cancel');
		},

		createIframe() {
			const self = this;
			const successCallback = (data) => { this.iframeLoadSuccess(data); };
			const failureCallback = self.iframeLoadFailure.bind(self);

			this.beginAsyncFlow();
			const iframePromise = new Promise((resolve, reject) => {
				chIframe.instantiateIframe(
					resolve,
					reject,
				);
			});

			iframePromise.then((data) => {
				successCallback(data);
			}).catch((error) => {
				failureCallback(error);
			});
		},

		destroyIframe() {
			chIframe.destroyIframe();
			this.hideIframeButtons();
			this.getFormContainer().hide();
			this.showAddButton();
		},

		submitIframe() {
			this.beginAsyncFlow();
			chIframe.submitCardForm(
				this.tokenConfig.storeUrl,
				(sessionId) => { this.beginTokenizationFlow(sessionId); },
				() => { this.iframeRunFailure(); },
			);
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

		unmaskCardNumber() {
			chIframe.unmask('cardNumber');
			const unmaskButton = this.getNumberUnmaskButton();
			const maskButton = this.getNumberMaskButton();

			unmaskButton.addClass('sdc-hidden');
			maskButton.removeClass('sdc-hidden');
		},

		maskCardNumber() {
			chIframe.mask('cardNumber');
			const unmaskButton = this.getNumberUnmaskButton();
			const maskButton = this.getNumberMaskButton();

			unmaskButton.removeClass('sdc-hidden');
			maskButton.addClass('sdc-hidden');
		},

		unmaskSecurityCode() {
			chIframe.unmask('securityCode');
			const unmaskButton = this.getSecurityUnmaskButton();
			const maskButton = this.getSecurityMaskButton();

			unmaskButton.addClass('sdc-hidden');
			maskButton.removeClass('sdc-hidden');
		},

		maskSecurityCode() {
			chIframe.mask('securityCode');
			const unmaskButton = this.getSecurityUnmaskButton();
			const maskButton = this.getSecurityMaskButton();

			unmaskButton.removeClass('sdc-hidden');
			maskButton.addClass('sdc-hidden');
		},

		iframeLoadSuccess(data) {
			this.hideAddButton();
			this.getFormContainer().show();
			this.showIframeButtons();
			this.endAsyncFlow();
			this.iframeValidHandler(false);
		},

		iframeLoadFailure(message) {
			this.endAsyncFlow();
			this.showError(message);
		},

		iframeRunFailure() {
			this.showError('Card capture failure. Please try again.');
			chIframe.resetIframe();
			this.endAsyncFlow();
		},

		iframeValidHandler(valid) {
			if (valid) {
				this.enableCreateButton();
			} else {
				this.disableCreateButton();
			}
		},

		beginAsyncFlow() {
			$('body').trigger('processStart');
		},

		endAsyncFlow() {
			$('body').trigger('processStop');
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

		beginTokenizationFlow(sessionId) {
			const self = this;

			// Callbacks
			const validateCallback = self.validateTokenizeResponse.bind(self);
			const successCallback = self.tokenizeSuccess.bind(self);
			const errorCallback = self.tokenizeFailure.bind(self);
			const completeCB = self.endAsyncFlow.bind(self);

			// Data
			const errorMessage = self.tokenizeErrorMsg;
			const customerId = self.tokenConfig.customer_id;
			const { storeUrl } = self.tokenConfig;
			const { tokenizeUrl } = self;
			const websiteId = self.tokenConfig.website_id;

			self.beginAsyncFlow();
			$.post({
				cache: false,
				url: [...[...[...storeUrl.concat(tokenizeUrl), '?session_id='].concat(sessionId), '&customer_id=']
					.concat(customerId), '&website_id=']
					.concat(websiteId),
				headers: {
					'Content-Type': 'application/json',
					'X-Requested-With': 'XMLHttpRequest',
				},
				success(response) {
					if (!validateCallback(response)) {
						errorCallback(errorMessage);
					}
					successCallback();
				},
				error(error) {
					errorCallback(errorMessage);
					console.log(error);
				},
				complete() {
					completeCB();
				},
			});
		},

		validateTokenizeResponse(response) {
			const responseObject = JSON.parse(response);
			const { maskedCC } = responseObject;
			const { expirationDate } = responseObject;

			return maskedCC !== undefined && expirationDate !== undefined;
		},

		tokenizeSuccess() {
			location.reload();
		},

		tokenizeFailure(message) {
			this.endAsyncFlow();
			this.showError(message);
			chIframe.resetIframe();
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

		getInvalidFieldMessages() {
			return this.tokenConfig.invalidFields;
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
});
