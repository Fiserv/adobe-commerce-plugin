define([
	'ko',
	'uiComponent',
	'Fiserv_Payments/js/ch-adapter',
	'Magento_Ui/js/modal/alert',
	'Magento_Ui/js/lib/view/utils/dom-observer',
	'jquery',
	'domReady!',
], (
	ko,
	Component,
	chIframe,
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

		/**
			* @returns {exports.initialize}
			*/
		initialize(config) {
			if (config.paymentConfig === undefined) {
				throw new TypeError('Required parameter paymentConfig not found!');
			}
			this.paymentConfig = config.paymentConfig;

			globalThis.instantiateIframe = () => { this.remoteInstantiate(); };

			if ($('input#p_method_fiserv_commercehub').is(':checked')) {
				this.initPayment();
				this.onActiveChange(true);
			}
		},

		/**
			 * @returns {exports.initialize}
			 */
		initPayment() {
			if (this.paymentInitiated === true) {
				return;
			}
			const config = this.paymentConfig;

			if (config === undefined) {
				throw new TypeError('Required parameter paymentConfig not found!');
			}

			const self = this;

			// defaults aren't being set for some reason...?
			self.paymentConfig = config;
			self.code = 'fiserv_commercehub';
			self.$selector = null;
			self.selector = 'edit_form';
			self.paymentMethodName = '[name="payment[method]"]';

			chIframe.initialize(
				self.paymentConfig,
				self.iframeLoadSuccess.bind(self),
				self.iframeValidHandler.bind(self),
				(brand) => { this.cardBrandChangeHandler(brand); },
				(data) => { this.fieldValidityHandler(data); },
				(data) => { this.fieldFocusHandler(data); },
			);
			this.initObservable();
			this.paymentInitiated = true;
		},

		remoteInstantiate() {
			if (globalThis.shouldInstantiateIframe !== undefined &&
					globalThis.shouldInstantiateIframe === true) {
				this.disableEventListeners();
				this.enableEventListeners();
				this.destroyIframe();
				this.createIframe();
				globalThis.shouldInstantiateIframe = false;
			}
		},

		/**
			 * Set list of observable attributes
			 * @returns {exports.initObservable}
			 */
		initObservable() {
			const self = this;

			self.$selector = $(`#${self.selector}`);
			this._super()
				.observe([
					'active',
				]);

			// re-init payment method events
			self.$selector.off(`changePaymentMethod.${self.code}`)
				.on(`changePaymentMethod.${self.code}`, self.changePaymentMethod.bind(self));

			return this;
		},

		enableSubmitButton() {
			this.getSubmitButton().prop('disabled', false);
		},

		disableSubmitButton() {
			this.getSubmitButton().prop('disabled', true);
		},

		getSubmitButton() {
			return $('button#tokenization-builder-create');
		},

		createIframe() {
			const self = this;
			const successCallback = self.iframeLoadSuccess.bind(self);
			const failureCallback = self.iframeLoadFailure.bind(self);

			self.beginAsyncFlow();
			const iframePromise = new Promise((resolve, reject) => {
				chIframe.instantiateIframe(
					resolve,
					reject,
				);
			});

			iframePromise.then((data) => {}).catch((error) => {
				failureCallback(error);
			});
		},

		destroyIframe() {
			chIframe.destroyIframe();
		},

		iframeLoadSuccess(data) {
			this.endAsyncFlow();
		},

		iframeLoadFailure(message) {
			this.endAsyncFlow();
			this.showError(message);
		},

		iframeRunFailure() {
			this.endAsyncFlow();
			this.showError('Card capture failure. Please try again.');
			chIframe.resetIframe();
		},

		iframeValidHandler(valid) {
			this.iframeValid = valid;
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
			alert({
				content: errorMessage,
			});
		},

		placeOrder(sessionId) {
			this.setPaymentSessionInput(sessionId);
			$(`#${this.selector}`).trigger('realOrder');
		},

		setPaymentSessionInput(sessionId) {
			this.getPaymentSessionInput().val(sessionId);
		},

		getPaymentSessionInput() {
			return $('input#fiserv_commercehub_payment_session');
		},

		/**
			* Begin order flow (i.e. secure card capture)
			*/
		startOrderFlow() {
			if (this.iframeValid) {
				chIframe.submitCardForm(
					this.paymentConfig.storeUrl,
					(sessionId) => { this.placeOrder(sessionId); },
					() => { this.iframeRunFailure(); },
				);
			} else {
				this.endAsyncFlow();
			}

			return false;
		},

		// Handle payment method switching
		isActive() {
			return $(`[value="${this.code}"]${this.paymentMethodName}`).prop('checked');
		},

		/**
			 * Triggered when payment changed
			 *
			 * @param {Boolean} isActive
			 */
		onActiveChange(isActive) {
			if (!isActive) {
				this.$selector.off(`submitOrder.${this.code}`);
				this.destroyIframe();

				return;
			}

			this.disableEventListeners();
			globalThis.order.addExcludedPaymentMethod(this.code);
			this.enableEventListeners();
			this.destroyIframe();
			this.createIframe();
		},

		/**
			 * Destroys SCC form when fiserv_commercehub not checked.
			 * isActive() not working with COD for some reason.
			 */
		watchPaymentMethods() {
			const self = this;

			$(self.paymentMethodName).click(function () {
				const selected = $(this).attr('value');

				if (selected === self.code) {
					self.destroyIframe();
					self.createIframe();
				} else {
					self.destroyIframe();
				}
			});
		},

		/**
			 * Enable/disable current payment method
			 * @param {Object} event
			 * @param {String} method
			 * @returns {exports.changePaymentMethod}
			 */
		changePaymentMethod(event, method) {
			this.active(method === this.code);
			this.onActiveChange(this.active());

			return this;
		},

		/**
			 * Enable form event listeners
			 */
		enableEventListeners() {
			this.$selector.on(`submitOrder.${this.code}`, this.startOrderFlow.bind(this));
			$('#sdc-unmask-number').on('click', () => { this.unmaskCardNumber(); });
			$('#sdc-mask-number').on('click', () => { this.maskCardNumber(); });
			$('#sdc-unmask-security').on('click', () => { this.unmaskSecurity(); });
			$('#sdc-mask-security').on('click', () => { this.maskSecurity(); });
		},

		/**
			 * Disable form event listeners
			 */
		disableEventListeners() {
			this.$selector.off('submitOrder');
			this.$selector.off('submit');
			$('#sdc-unmask-number').off('click');
			$('sdc-mask-number').off('click');
			$('#sdc-unmask-security').off('click');
			$('sdc-mask-security').off('click');
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
			return this.paymentConfig.invalidFields;
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

		getNumberUnmaskButton() {
			return $('#sdc-unmask-number');
		},

		getSecurityUnmaskButton() {
			return $('#sdc-unmask-security');
		},

		getNumberMaskButton() {
			return $('#sdc-mask-number');
		},

		getSecurityMaskButton() {
			return $('#sdc-mask-security');
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

		unmaskSecurity() {
			chIframe.unmask('securityCode');
			const unmaskButton = this.getSecurityUnmaskButton();
			const maskButton = this.getSecurityMaskButton();

			unmaskButton.addClass('sdc-hidden');
			maskButton.removeClass('sdc-hidden');
		},

		maskSecurity() {
			chIframe.mask('securityCode');
			const unmaskButton = this.getSecurityUnmaskButton();
			const maskButton = this.getSecurityMaskButton();

			unmaskButton.removeClass('sdc-hidden');
			maskButton.addClass('sdc-hidden');
		},
	});
});
