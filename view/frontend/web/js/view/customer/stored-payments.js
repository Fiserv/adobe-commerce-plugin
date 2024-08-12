define([
	'ko',
		'uiComponent',
		'Fiserv_Payments/js/ch-adapter',
		'Magento_Checkout/js/model/full-screen-loader',
		'Magento_Ui/js/model/messageList',
		'jquery',
		'domReady!'
	], function(
		ko,
		Component,
		chIframe,
		fullScreenLoader,
		globalMessageList,
		$
	) {
		'use strict';

		return Component.extend({
			defaults: {
				tokenConfig: {},
				tokenizeUrl: "fiserv/vault/tokenizesession",
				tokenizeErrorMsg: "Something went wrong. Please try again later."
			},

			/**
			 * @returns {exports.initialize}
			 */
			initialize: function (config) {
				if (config.tokenConfig === undefined) { 
					throw new Error('Required parameter tokenConfig not found!');
				}

				var self = this;
				
				this.tokenConfig = config.tokenConfig;
				chIframe.initialize(
					this.tokenConfig,
					this.iframeLoadSuccess.bind(this),
					this.iframeValidHandler.bind(this),
					(brand) => { this.cardBrandChangeHandler(brand); },
					(data) => { this.fieldValidityHandler(data); },
					(data) => { this.fieldFocusHandler(data); }
				);

				this.getFormContainer().hide();
				this.showTokenizationContainer();

				self._super();
				return self;
			},
			
			getFormContainer: function()
			{
				return $('#sdc-container');
			},
			
			showTokenizationContainer: function() {
				this.getTokenizationContainer().show();
				this.disableCreateButton();
			},

			getTokenizationContainer: function() {
				return $('#tokenization-builder-container');
			},

			hideAddButton: function() {
				this.getAddButton().hide();
			},

			showAddButton: function() {
				this.getAddButton().show();
			},

			getAddButton: function() {
				return $('button#tokenization-builder-add');
			},
	
			hideIframeButtons: function() {
				this.getIframeButtons().hide();
				this.disableIframeButtons();
			},

			showIframeButtons: function() {
				this.getIframeButtons().show();
				this.enableIframeButtons();
			},

			enableIframeButtons: function() {
				this.enableCancelButton();
				this.enableCreateButton();
			},

			disableIframeButtons: function() {
				this.disableCancelButton();
				this.disableCreateButton();
			},

			getIframeButtons: function() {
				return $('div#tokenization-iframe-buttons');
			},

			enableCreateButton: function() {
				this.getCreateButton().prop("disabled", false);
			},

			disableCreateButton: function() {
				this.getCreateButton().prop("disabled", true);
			},

			getCreateButton: function() {
				return $('button#tokenization-builder-create');
			},

			enableCancelButton: function() {
				this.getCancelButton().prop("disabled", false);
			},

			disableCancelButton: function() {
				this.getCancelButton().prop("disabled", true);
			},

			getCancelButton: function() {
				return $('button#tokenization-builder-cancel');
			},
	
			createIframe: function() {
				var self = this;
				let successCb = (data) => { this.iframeLoadSuccess(data); };
				let failureCb = self.iframeLoadFailure.bind(self);

				this.beginAsyncFlow();
				let iframePromise = new Promise((resolve, reject) => {
					chIframe.instantiateIframe(
						resolve, 
						reject
					);
				});
				iframePromise.then((data) => {
					successCb(data);
				}).catch((error) =>{
					failureCb(error);
				})

			},

			destroyIframe: function() {
				chIframe.destroyIframe();
				this.hideIframeButtons();
				this.getFormContainer().hide();
				this.showAddButton();
			},

			submitIframe: function() {
				this.beginAsyncFlow();
				chIframe.submitCardForm(
					this.tokenConfig['storeUrl'], 
					(sessionId) => { this.beginTokenizationFlow(sessionId); },
					() => { this.iframeRunFailure(); }
				);
			},

			iframeLoadSuccess: function (data) {
				this.hideAddButton();
				this.getFormContainer().show();
				this.showIframeButtons();
				this.endAsyncFlow();
				this.iframeValidHandler(false);
			},

			iframeLoadFailure: function(message) {
				this.endAsyncFlow();
				this.showError(message);
			},

			iframeRunFailure: function() {
				this.showError("Card capture failure. Please try again."); 
				chIframe.resetIframe();
				this.endAsyncFlow();
			},

			iframeValidHandler: function(valid) {
				if (valid) {
					this.enableCreateButton();
				} else {
					this.disableCreateButton();
				}
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
				globalMessageList.addErrorMessage({
					message: errorMessage
				});
			},

			beginTokenizationFlow: function(sessionId) {
				let self = this;

				// Callbacks
				let validateCb = self.validateTokenizeResponse.bind(self);
				let successCb = self.tokenizeSuccess.bind(self);
				let errorCb = self.tokenizeFailure.bind(self);
				let completeCB = self.endAsyncFlow.bind(self);

				// Data
				let errorMsg = self.tokenizeErrorMsg;
				let customerId = self.tokenConfig.customer_id;
				let storeUrl = self.tokenConfig.storeUrl;
				let tokenizeUrl = self.tokenizeUrl;
				let websiteId = self.tokenConfig.website_id;

				self.beginAsyncFlow();
				$.post({
					cache: false,
					url: storeUrl.concat(tokenizeUrl).concat("?session_id=").concat(sessionId).concat("&customer_id=").concat(customerId).concat("&website_id=").concat(websiteId),
					headers: {
						"Content-Type": "application/json",
						"X-Requested-With": "XMLHttpRequest"
					},
					success: function(response) {
						if (!validateCb(response)) {
							errorCb(errorMsg);
						}
						successCb();
					},
					error: function(err) {
						errorCb(errorMsg);
						console.log(err);
					},
					complete: function() {
						completeCB();
					}
				});
			},

			validateTokenizeResponse: function(response) {
				let maskedCC = response["maskedCC"];
				let expirationDate = response["expirationDate"];

				return (typeof(maskedCC) !== "undefined" && typeof(expirationDate) !== "undefined");
			},

			tokenizeSuccess: function() {
				location.reload();
			},

			tokenizeFailure: function(message) {
				this.showError(message);
				location.reload();
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
				return this.tokenConfig['invalidFields'];
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
