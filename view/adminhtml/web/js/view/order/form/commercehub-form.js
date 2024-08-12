define([
		'ko',
		'uiComponent',
		'Fiserv_Payments/js/ch-adapter',
		'Magento_Ui/js/modal/alert',
		'Magento_Ui/js/lib/view/utils/dom-observer',
		'jquery',
		'domReady!'
	], function(
		ko,
		Component,
		chIframe,
		alert,
		domObserver,
		$
	) {
		'use strict';

		return Component.extend({
			defaults: {
				paymentSessionInputId: 'fiserv_commercehub_payment_session',
				iframeValid: false,
				imports: {
					onActiveChange: 'active'
				}
			},

			/**
			* @returns {exports.initialize}
			*/
			initialize: function (config) {
				
				if (typeof(config.paymentConfig) === "undefined") { 
					throw new Error('Required parameter paymentConfig not found!');
				}
				this.paymentConfig = config.paymentConfig;
				
				window.instantiateIframe = () => { this.remoteInstantiate(); };

				if($('input#p_method_fiserv_commercehub').is(':checked')) {
					this.initPayment();
					this.onActiveChange(true);
				}
			},

			/**
			 * @returns {exports.initialize}
			 */
			initPayment: function () {
				if(this.paymentInitiated === true) {
					return;
				}
				let config = this.paymentConfig;
				if (typeof(config) === "undefined") { 
					throw new Error('Required parameter paymentConfig not found!');
				}

				var self = this;
				
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
					(data) => { this.fieldFocusHandler(data); }
				);
				this.initObservable();
				this.paymentInitiated = true;
			},


			remoteInstantiate: function() 
			{
				if (typeof(window.shouldInstantiateIframe) !== 'undefined' &&
					window.shouldInstantiateIframe === true)
				{
					this.destroyIframe();
					this.createIframe();
					window.shouldInstantiateIframe = false;
				}
			},

			/**
			 * Set list of observable attributes
			 * @returns {exports.initObservable}
			 */
			initObservable: function () {
				var self = this;

				self.$selector = $('#' + self.selector);
				this._super()
					.observe([
						'active'
					]);

				// re-init payment method events
				self.$selector.off('changePaymentMethod.' + self.code)
					.on('changePaymentMethod.' + self.code, self.changePaymentMethod.bind(self));

				return this;
			},

			enableSubmitButton: function() {
				this.getSubmitButton().prop("disabled", false);
			},

			disableSubmitButton: function() {
				this.getSubmitButton().prop("disabled", true);
			},

			getSubmitButton: function() {
				return $('button#tokenization-builder-create');
			},
	
			createIframe: function() {
				var self = this;
				let successCb = self.iframeLoadSuccess.bind(self);
				let failureCb = self.iframeLoadFailure.bind(self);
				
				self.beginAsyncFlow();
				let iframePromise = new Promise((resolve, reject) => {
					chIframe.instantiateIframe(
						resolve, 
						reject
					);
				});
				iframePromise.then((data) => {
				
				}).catch((error) =>{
					failureCb(error);
				})

			},

			destroyIframe: function() {
				chIframe.destroyIframe();
			},

			iframeLoadSuccess: function (data) {
				this.endAsyncFlow();
			},

			iframeLoadFailure: function(message) {
				this.endAsyncFlow();
				this.showError(message);
			},

			iframeRunFailure: function() {
				this.showError("Card capture failure. Please try again."); 
				chIframe.resetIframe();
			},

			iframeValidHandler: function(valid) {
				this.iframeValid = valid;
			},

			beginAsyncFlow: function() {
				 $('body').trigger('processStart');
			},

			endAsyncFlow: function() {
				 $('body').trigger('processStop');	
			},

			/**
			 * Show error message
			 *
			 * @param {String} errorMessage
			 * @private
			 */
			showError: function (errorMessage) {
				alert({
					content: errorMessage
				});
			},

			placeOrder: function(sessionId) {
				this.setPaymentSessionInput(sessionId);
				this.endAsyncFlow();
				$('#' + this.selector).trigger('realOrder');
			},

			setPaymentSessionInput: function(sessionId) {
				this.getPaymentSessionInput().val(sessionId);	
			},

			getPaymentSessionInput: function() {
				return $("input#fiserv_commercehub_payment_session");
			},

			/**
			* Begin order flow (i.e. secure card capture)
			*/
			startOrderFlow: function () {
				if (this.iframeValid) {
					this.beginAsyncFlow();
					chIframe.submitCardForm(
						this.paymentConfig['storeUrl'], 
						(sessionId) => { this.placeOrder(sessionId); },
						() => { this.iframeRunFailure(); }
					)
				} else {
					this.endAsyncFlow();
				}
				return false;
			},

			// Handle payment method switching
			isActive: function () {
				return $('[value="' + this.code + '"]' + this.paymentMethodName).prop('checked');
			},


			/**
			 * Triggered when payment changed
			 *
			 * @param {Boolean} isActive
			 */
			onActiveChange: function (isActive) {
				if (!isActive) {
					this.$selector.off('submitOrder.' + this.code);
					this.destroyIframe();
					return;
				}

				this.disableEventListeners();
				window.order.addExcludedPaymentMethod(this.code);
				this.enableEventListeners();
				this.destroyIframe();
				this.createIframe();
			},

			/** 
			 * Destroys SCC form when fiserv_commercehub not checked.
			 * isActive() not working with COD for some reason.
			 */
			watchPaymentMethods: function () {
				let self = this;
				$(self.paymentMethodName).click(function() {
					let selected = $(this).attr('value');
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
			changePaymentMethod: function (event, method) {
				this.active(method === this.code);
				this.onActiveChange(this.active());	
				return this;
			},

			/**
			 * Enable form event listeners
			 */
			enableEventListeners: function () {
				this.$selector.on('submitOrder.' + this.code, this.startOrderFlow.bind(this));
			},

			/**
			 * Disable form event listeners
			 */
			disableEventListeners: function () {
				this.$selector.off('submitOrder');
				this.$selector.off('submit');
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

			getInvalidFieldMessages: function() {
				return this.paymentConfig['invalidFields'];
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
