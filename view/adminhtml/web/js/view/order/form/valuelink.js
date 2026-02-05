define([
	'ko',
	'uiComponent',
	'Fiserv_Payments/js/ch-adapter2',
	'Magento_Ui/js/modal/alert',
	'Magento_Ui/js/lib/view/utils/dom-observer',
	'jquery',
	'domReady!',
], (
	ko,
	Component,
	sdcv2,
	alert,
	domObserver,
	$,
) => {
	'use strict';

	return Component.extend({
		defaults: {
			paymentSessionInputId: 'fiserv_commercehub_payment_session',
			iframeValid: false,
			imports: {
				onActiveChange: 'active',
			},
		},
		balanceInquiryGeneralErrorMessage: 'Invalid gift card. Please try again or use another form of payment.',
		valuelinkBalanceUrl: 'fiserv/valuelink/getvaluelinkbalance',
		addedMessage: 'Gift card added successfully',
		emptyMessage: 'Gift card has no balance',
		addErrorMessage: 'An error occurred. Gift card was not added.',

		/**
			* @returns {exports.initialize}
			*/
		initialize(config) {
			if (config.valuelinkConfig === undefined) {
				throw new TypeError('Required parameter valuelinkConfig not found!');
			}
			this.paymentConfig = config;
			this.paymentConfig.formConfig = this.paymentConfig.valuelinkConfig;

			// Intercept order.loadArea in order to add items block to reload list if necessary
			if (globalThis.loadAreaReference === undefined && order.loadArea) {
				globalThis.loadAreaReference = order.loadArea;
			}
			order.loadArea = this.loadArea;

			if (this.isValuelinkEnabled()) {
				this.code = 'fiserv_valuelink';

				if (globalThis.valuelinkFormKey !== undefined) {
					this.disableEventListeners();
					this.destroyGiftCardForm();
				}

				globalThis.valuelinkFormKey = sdcv2.initializeForm(
					this.paymentConfig,
					() => { this.endIframeFlow(); },
					(valid) => { this.formValidHandler(valid); },
					(brand) => { /* DO NOTHING */ },
					(data) => { this.fieldValidityHandler(data); },
					(data) => { this.fieldFocusHandler(data); },
				);
				globalThis.showValuelinkSuccessMsg = (message) => { this.showSuccessMessage(message); };
				globalThis.showValuelinkErrorMsg = (message) => { this.showErrorMessage(message); };

				this.createGiftCardForm();
			}

			return this;
		},

		createGiftCardForm() {
			const iframePromise = new Promise((resolve, reject) => {
				sdcv2.instantiateIframe(
					globalThis.valuelinkFormKey,
					'GIFT',
					resolve,
					reject,
				);
			});

			iframePromise.then((data) => {}).catch((error) => {
				this.iframeLoadFailure(error);
			});

			this.enableEventListeners();
		},

		destroyGiftCardForm() {
			sdcv2.destroyIframe(globalThis.valuelinkFormKey);
		},

		getCode() {
			return 'fiserv_valuelink';
		},

		/**
			 * Is Valuelink enabled
			 */
		isValuelinkEnabled() {
			return this.paymentConfig.useValuelink;
		},

		getBalanceButton() {
			return $(`#${this.getCode()}-get-gift-card-balance`);
		},

		getApplyButton() {
			return $(`#${this.getCode()}-apply-gift-card`);
		},

		getContainer() {
			return $(`#${this.getCode()}-placer`);
		},

		getCardInfoPanel() {
			return $(`#${this.getCode()}-card-info-panel`);
		},

		getCardBalanceInfo() {
			return $(`#${this.getCode()}-card-balance-info`);
		},

		getSessionIdInput() {
			return $(`#${this.getCode()}-sessionId`);
		},

		getBalanceInput() {
			return $(`#${this.getCode()}-balance`);
		},

		formValidHandler(valid) {
			this.isFormValid = valid;
			if (this.isFormValid) {
				this.getBalanceButton().prop('disabled', false);
				this.getApplyButton().prop('disabled', false);
			} else {
				this.resetFormPanel();
			}
		},

		resetFormPanel() {
			this.hideCardInfoPanel();
			this.getBalanceInput().val('');
			this.getCardBalanceInfo().text('');
			this.getSessionIdInput().val('');
			this.getBalanceButton().prop('disabled', true);
			this.getApplyButton().prop('disabled', true);
		},

		iframeLoadSuccess(data) {},

		iframeLoadFailure(message) {
			this.showError(message);
		},

		iframeRunFailure() {
			this.showError('Card capture failure. Please try again.');
			this.resetFormPanel();
		},

		iframeValidHandler(valid) {
			this.iframeValid = valid;
		},

		startIframeFlow() {
			$('body').trigger('processStart');
		},

		endIframeFlow() {
			$('body').trigger('processStop');
		},

		/**
			 * Set gift card.
			 */
		setValuelinkCard() {
			if (!this.isFormValid) {
				this.formValidHandler(false);

				return;
			}

			const balance = this.getBalanceInput().val();
			const sessionId = this.getSessionIdInput().val();

			if (!sessionId) {
				this.captureCardForm((sessionId) => { this.checkBalanceAndSetCardCb(sessionId); });

				return;
			}

			if (!balance) {
				this.checkBalanceAndSetCardCb(sessionId);

				return;
			}

			if (Number.parseFloat(balance) === 0) {
				this.showErrorMessage(this.emptyMessage);

				return;
			}

			this.setValuelinkCardAction(sessionId, balance);
		},

		setValuelinkCardAction(sessionId, balance) {
			const data = {};

			data.valuelink_add = sessionId;
			data.valuelink_balance = balance;
			order.loadArea(['totals', 'billing_method', 'items'], true, data, () => {
				if ($('#valuelink-card-'.concat(sessionId)).length > 0) {
					this.showSuccessMessage(this.addedMessage);
				} else {
					this.showErrorMessage(this.addErrorMessage);
				}
			});
		},

		loadArea(area, indicator, parameters, callback) {
			if (!area.includes('items') && parameters['order[shipping_method]'] !== undefined && $('#valuelink-added-cards-container').length > 0) {
				area.push('items');
			}

			return globalThis.loadAreaReference.apply(order, [area, indicator, parameters, callback]);
		},

		/**
			 * Show error message
			 *
			 * @param {String} errorMessage
			 * @private
			 */
		showError(errorMessage) {
			alert({
				content: errorMessage,
			});
		},

		/**
			 * Check balance.
			 */
		checkBalance() {
			// if (this.validate()) {
			//    getGiftCardAction.check(this.giftCartCode());
			// }

			this.captureCardForm((sessionId) => { this.checkBalanceCaptureCb(sessionId); });
		},

		checkBalanceCaptureCb(sessionId) {
			this.cardCaptureSuccess(sessionId);
			this.startIframeFlow();
			this.getValuelinkBalance(
				sessionId,
				this.paymentConfig.storeUrl,
				(data) => { this.balanceInquirySuccess(data); },
				(error) => { this.balanceInquiryFailure(error.responseJSON.message); },
			);
		},

		checkBalanceAndSetCardCb(sessionId) {
			this.cardCaptureSuccess(sessionId);
			this.startIframeFlow();
			this.getValuelinkBalance(
				sessionId,
				this.paymentConfig.storeUrl,
				(data) => { this.balanceInquirySuccess(data); this.setValuelinkCard(); },
				(error) => { this.balanceInquiryFailure(error.responseJSON.message); },
			);
		},

		balanceInquirySuccess(data) {
			this.endIframeFlow();

			const balanceInquiryValidationResponse = this.validateBalanceInquiry(data);

			if (!balanceInquiryValidationResponse.isValid) {
				return this.balanceInquiryFailure(balanceInquiryValidationResponse.message);
			}

			if (Number.parseFloat(data.valuelink_balance.endingBalance) <= 0) {
				this.getApplyButton().prop('disabled', true);
			}

			this.getBalanceButton().prop('disabled', true);
			this.showCardInfoPanel(data.valuelink_balance.endingBalance, data.valuelink_balance.currency);
		},

		showCardInfoPanel(balance, currency) {
			this.getBalanceInput().val(balance);
			this.getCardBalanceInfo().text(this.formatBalanceAsCurrency(balance, currency));
			this.getCardInfoPanel().show();
		},

		hideCardInfoPanel() {
			this.getCardInfoPanel().hide();
		},

		formatBalanceAsCurrency(balance, currency) {
			const formatter = new Intl.NumberFormat('en-US', {
				style: 'currency',
				currency,
			});

			return formatter.format(balance);
		},

		balanceInquiryFailure(error) {
			sdcv2.resetIframe(globalThis.valuelinkFormKey);
			this.endIframeFlow();
			if (typeof error === 'string') { this.showErrorMessage(error); } else { this.showErrorMessage('Invalid Gift Card or CVV'); }
		},

		validateBalanceInquiry(response) {
			const valid =
				response.valuelink_balance !== undefined &&
						response.valuelink_balance.currency !== undefined &&
						response.valuelink_balance.endingBalance !== undefined &&
						response.valuelink_balance.responseMessage !== undefined &&
						!isNaN(Number.parseFloat(response.valuelink_balance.endingBalance)) &&
						response.valuelink_balance.responseMessage === 'Approved';

			let message = response.valuelink_balance !== undefined &&
						response.valuelink_balance.responseMessage !== undefined ?
				response.valuelink_balance.responseMessage :
				this.balanceInquiryGeneralErrorMessage;

			// Remove this line one day with a propper message mapper...
			message = message === 'Invalid SKU/EAN/SCV' ? 'Invalid security code provided' : this.balanceInquiryGeneralErrorMessage;

			return { isValid: valid, message };
		},

		captureCardForm(successCallback) {
			if (this.isFormValid === true) {
				this.startIframeFlow();
				sdcv2.submitCardForm(
					globalThis.valuelinkFormKey,
					this.paymentConfig.storeUrl,
					successCallback,
					() => { this.cardCaptureFailure(); },
				);
			} else {
				console.log('Valuelink gift card form invalid');
			}
		},

		cardCaptureSuccess(sessionId) {
			this.endIframeFlow();
			this.getSessionIdInput().val(sessionId);
		},

		cardCaptureFailure() {
			this.endIframeFlow();
			sdcv2.resetIframe(globalThis.valuelinkFormKey);
		},

		getValuelinkBalance(sessionId, storeUrl, successCallback, errorCallback) {
			$.ajax({
				url: `${storeUrl + this.valuelinkBalanceUrl}?sessionId=${sessionId}`,
				cache: false,
				dataType: 'json',
				type: 'GET',
				success(response) {
					successCallback(response);
				},
				error(error) {
					errorCallback(error);
				},
			});
		},

		enableEventListeners() {
			this.getBalanceButton().on('click', () => { this.checkBalance(); });
			this.getApplyButton().on('click', () => { this.setValuelinkCard(); });
		},

		disableEventListeners() {
			this.getBalanceButton().off('click');
			this.getApplyButton().off('click');
		},

		getSdcFieldFrame(name) {
			switch (name) {
				case 'cardNumber': {
					return $('#valuelink-sdc-card-number-frame');
				}
				case 'securityCode': {
					return $('#valuelink-sdc-security-code-frame');
				}
			}
		},

		getInvalidFieldMessages() {
			return this.paymentConfig.invalidFields;
		},

		getSdcFieldInvalidMessageContainer(name) {
			switch (name) {
				case 'cardNumber': {
					return $('#valuelink-sdc-card-number-invalid-message');
				}
				case 'securityCode': {
					return $('#valuelink-sdc-security-code-invalid-message');
				}
			}
		},

		getSdcInvalidFieldMessageText(name) {
			switch (name) {
				case 'cardNumber': {
					return this.getInvalidFieldMessages().cardNumber;
				}
				case 'securityCode': {
					return this.getInvalidFieldMessages().securityCode;
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

		showSuccessMessage(message) {
			this.clearErrorMessage();
			this.getValuelinkSuccessMessage().text(message);
			this.getValuelinkSuccessMessageContainer().show();

			setTimeout(() => {
				this.clearSuccessMessage();
			}, 5000);
		},

		showErrorMessage(message) {
			this.clearSuccessMessage();
			this.getValuelinkErrorMessage().text(message);
			this.getValuelinkErrorMessageContainer().show();

			setTimeout(() => {
				this.clearErrorMessage();
			}, 5000);
		},

		clearSuccessMessage() {
			this.getValuelinkSuccessMessage().text('');
			this.getValuelinkSuccessMessageContainer().hide();
		},

		clearErrorMessage() {
			this.getValuelinkErrorMessage().text('');
			this.getValuelinkErrorMessageContainer().hide();
		},

		getValuelinkSuccessMessageContainer() {
			return $('#valuelink-success-message-container');
		},

		getValuelinkSuccessMessage() {
			return $('#valuelink-success-message');
		},

		getValuelinkErrorMessageContainer() {
			return $('#valuelink-error-message-container');
		},

		getValuelinkErrorMessage() {
			return $('#valuelink-error-message');
		},
	});
});
