/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

define([
	'jquery',
	'ko',
	'uiComponent',
	'Fiserv_Payments/js/action/set-gift-card-information',
	'Magento_Checkout/js/model/totals',
	'Fiserv_Payments/js/model/valuelink/valuelink-messages',
	'Fiserv_Payments/js/ch-adapter2',
	'Magento_Checkout/js/model/quote',
	'Magento_Catalog/js/price-utils',
	'Magento_Checkout/js/model/full-screen-loader',
	'Magento_Checkout/js/model/error-processor',
	'mage/validation',
], ($, ko, Component, setGiftCardAction, totals, messageList, sdcv2, quote, priceUtilities, fullScreenLoader, errorProcessor) => {
	'use strict';

	const config = structuredClone(globalThis.checkoutConfig.payment.fiserv_commercehub);
	const valuelinkConfig = structuredClone(globalThis.checkoutConfig.payment.fiserv_payments.fiserv_valuelink);

	config.formConfig = valuelinkConfig.valuelinkConfig;

	return Component.extend({
		defaults: {
			template: 'Fiserv_Payments/payment/commercehub/valuelink_form',
			balanceInquiryGeneralErrorMessage: 'Invalid gift card. Please try again or use another form of payment.',
		},

		valuelinkBalanceUrl: 'fiserv/valuelink/getvaluelinkbalance',
		formKey: undefined,
		isFormValid: false,
		showClearButton: ko.observable(false), // Observable to control visibility of Clear button

		/** @inheritdoc */
		initObservable() {
			this._super();

			return this;
		},

		initialize() {
			this._super();

			if (this.isValuelinkEnabled()) {
				this.formKey = sdcv2.initializeForm(
					config,
					() => { this.endIframeFlow(); },
					(valid) => { this.formValidHandler(valid); },
					(brand) => { /* DO NOTHING */ },
					(data) => { this.fieldValidityHandler(data); },
					(data) => { this.fieldFocusHandler(data); },
				);
			}

			return this;
		},

		getCode() {
			return 'fiserv_valuelink';
		},

		/**
		 * Is Valuelink enabled
		 */
		isValuelinkEnabled() {
			return config.isActive && valuelinkConfig.isActive;
		},

		/**
		 * Get Valuelink title
		 */
		getValuelinkTitle() {
			return valuelinkConfig.valuelink_title;
		},

		/**
		* Set gift card.
		*/
		async setGiftCard() {
			if (!this.isFormValid) {
				this.formValidHandler(false);

				return;
			}

			let balance = this.getBalanceInput().val();
			let sessionId = this.getSessionIdInput().val();

			try {
				if (!sessionId) {
					sessionId = await this.captureCardFormAsync();
				}

				if (!balance) {
					balance = await this.checkBalanceCaptureCb(sessionId);
					if (!balance) {
						return;
					}
					this.getBalanceInput().val(balance); // Ensure input is updated
				}

				if (Number.parseFloat(balance) === 0) {
					this.showErrorMessage('Gift card has no balance.');

					return;
				}

				setGiftCardAction(sessionId, balance);

				sdcv2.resetIframe(this.formKey);
				this.resetFormPanel();
			} catch (error) {
				console.error('Error setting gift card:', error);
				this.showErrorMessage('An error occurred while processing the gift card.');
			}
		},
		captureCardFormAsync() {
			return new Promise((resolve, reject) => {
				this.captureCardForm((sessionId) => {
					if (sessionId) {
						resolve(sessionId);
					} else {
						reject(new Error('Failed to capture card form.'));
					}
				});
			});
		},

		showErrorMessage(message) {
			messageList.clear();
			messageList.addErrorMessage({ message });
		},

		/**
		* Check balance.
		*/
		async checkBalance() {
			this.captureCardForm(async (sessionId) => { await this.checkBalanceCaptureCb(sessionId); });
		},

		/**
		 * Check balance only (no recursive call to setGiftCard).
		 * Returns the balance value if successful, otherwise null.
		 */
		async checkBalanceCaptureCb(sessionId) {
			try {
				this.cardCaptureSuccess(sessionId);
				this.startIframeFlow();

				const data = await this.getValuelinkBalanceAsync(sessionId, globalThis.checkoutConfig.payment.fiserv_payments.storeUrl);

				this.balanceInquirySuccess(data);

				// Return the balance value
				return data.valuelink_balance.endingBalance;
			} catch (error) {
				this.balanceInquiryFailure(error.responseJSON?.message || error.message);

				return null;
			}
		},

		/**
		 * Get Valuelink balance asynchronously.
		 */
		getValuelinkBalanceAsync(sessionId, storeUrl) {
			return new Promise((resolve, reject) => {
				this.getValuelinkBalance(
					sessionId,
					storeUrl,
					(data) => resolve(data),
					(error) => reject(error),
				);
			});
		},

		balanceInquirySuccess(data) {
			this.endIframeFlow();

			const balanceInquiryValidationResponse = this.validateBalanceInquiry(data);

			if (!balanceInquiryValidationResponse.isValid) {
				throw new Error(balanceInquiryValidationResponse.message);
			}

			if (Number.parseFloat(data.valuelink_balance.endingBalance) <= 0) {
				this.getApplyButton().prop('disabled', true);
			}

			this.getBalanceButton().prop('disabled', true);
			this.showCardInfoPanel(data.valuelink_balance.endingBalance, data.valuelink_balance.currency);
			this.showClearButton(true); // Show the Clear button when a valid balance is displayed
		},

		showCardInfoPanel(balance, currency) {
			this.getBalanceInput().val(balance);
			this.getCardBalanceInfo().text(this.formatBalanceAsCurrency(balance, currency));
			this.getCardInfoPanel().show();
		},

		hideCardInfoPanel() {
			this.getCardInfoPanel().hide();
		},

		resetFormPanel() {
			this.hideCardInfoPanel();
			this.getBalanceInput().val('');
			this.getCardBalanceInfo().text('');
			this.getSessionIdInput().val('');
			this.getBalanceButton().prop('disabled', true);
			this.getApplyButton().prop('disabled', true);
			this.showClearButton(false); // Hide the Clear button when form is reset
		},

		clearFields() {
			sdcv2.resetIframe(this.formKey); // Reset the entire iframe
			this.resetFormPanel(); // Reset the form and hide the Clear button
		},

		formatBalanceAsCurrency(balance, currency) {
			const formatter = new Intl.NumberFormat('en-US', {
				style: 'currency',
				currency,
			});

			return formatter.format(balance);
		},

		balanceInquiryFailure(error) {
			sdcv2.resetIframe(this.formKey);
			this.endIframeFlow();
			this.showErrorMessage(error);
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
					this.formKey,
					globalThis.checkoutConfig.payment.fiserv_payments.storeUrl,
					successCallback,
					() => { this.cardCaptureFailure(); },
				);
			}
		},

		cardCaptureSuccess(sessionId) {
			this.endIframeFlow();
			this.getSessionIdInput().val(sessionId);
		},

		cardCaptureFailure() {
			this.endIframeFlow();
			sdcv2.resetIframe(this.formKey);
		},

		toggleValuelinkForm() {
			if (this.isContainerActive()) {
				this.destroyGiftCardForm();
				this.formValidHandler(false);
			} else {
				this.createGiftCardForm();
			}
		},

		activateValuelinkForm() {
			if (!this.isContainerActive()) {
				this.createGiftCardForm();
			}
		},

		deactivateValuelinkForm() {
			if (this.isContainerActive()) {
				this.destroyGiftCardForm();
			}
		},

		createGiftCardForm() {
			const iframePromise = new Promise((resolve, reject) => {
				this.startIframeFlow();
				sdcv2.instantiateIframe(
					this.formKey,
					'GIFT',
					resolve,
					reject,
				);
			});

			iframePromise.then((data) => {}).catch((error) => {
				this.iframeLoadFailure(error);
			});
		},

		destroyGiftCardForm() {
			sdcv2.destroyIframe(this.formKey);
		},

		isContainerActive() {
			return this.getContainer().hasClass('_active');
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

		startIframeFlow() {
			fullScreenLoader.startLoader();
		},

		endIframeFlow() {
			fullScreenLoader.stopLoader();
		},

		iframeLoadFailure(error) {
			errorProcessor.process('Unable to load gift card form. Please try again later.', messageList);
			this.endIframeFlow();
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
					return config.invalidFields.cardNumber;
				}
				case 'securityCode': {
					return config.invalidFields.securityCode;
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
