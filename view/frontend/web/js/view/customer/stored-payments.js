define([
	'ko',
		'uiComponent',
		'Fiserv_Payments/js/ch-adapter',
        'Fiserv_Payments/js/action/create-commercehub-session',
		'Magento_Checkout/js/model/full-screen-loader',
		'Magento_Ui/js/model/messageList',
		'jquery',
		'mage/translate',
		'domReady!'
	], function(
		ko,
		Component,
		chIframe,
        chSession,
		fullScreenLoader,
		globalMessageList,
		$,
		$t
	) {
		'use strict';

		return Component.extend({
			defaults: {
				tokenConfig: {},
				tokenizeUrl: "fiserv/vault/tokenizesession",
				tokenizeErrorMsg: $t("Something went wrong. Please try again later."),
                paymentSessionData: undefined,
                contextId: undefined,
			},

			/**
			 * @returns {exports.initialize}
			 */
			initialize: function (config) {
				if (config.tokenConfig === undefined) { 
					throw new Error($t('Required parameter tokenConfig not found!'));
				}
				
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

				this._super();
				return this;
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

            initCheckoutSdk: async function()
            {
                const credsResponse = await chSession({ isTokenization: true });
                this.paymentSessionData = credsResponse["ch_credentials"];

                this.contextId = crypto.randomUUID();

                await chIframe.initSdk(
                    this.tokenConfig,
                    this.paymentSessionData,
                    this.contextId,
                );
            },
	
			createIframe: async function() {
				this.beginAsyncFlow();
				
				await this.initCheckoutSdk();

				let iframePromise = new Promise((resolve, reject) => {
					chIframe.instantiateIframe(
						resolve, 
						reject
					);
				});
				iframePromise.then((data) => {
					(data) => { this.iframeLoadSuccess(data); };
				}).catch((error) =>{
					(error) => { this.iframeLoadFailure(error); };
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
				chIframe.submitIframeForm(
					() => { this.beginTokenizationFlow(); },
					() => { this.iframeRunFailure(); }
				);
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

			unmaskCardNumber: function() {
				chIframe.unmask('cardNumber');
				let unmaskButton = this.getNumberUnmaskButton();
				let maskButton = this.getNumberMaskButton();
				unmaskButton.addClass('sdc-hidden');
				maskButton.removeClass('sdc-hidden');
			},

			maskCardNumber: function() {
				chIframe.mask('cardNumber');
				let unmaskButton = this.getNumberUnmaskButton();
				let maskButton = this.getNumberMaskButton();
				unmaskButton.removeClass('sdc-hidden');
				maskButton.addClass('sdc-hidden');
			},

			unmaskSecurityCode: function() {
				chIframe.unmask('securityCode');
				let unmaskButton = this.getSecurityUnmaskButton();
				let maskButton = this.getSecurityMaskButton();
				unmaskButton.addClass('sdc-hidden');
				maskButton.removeClass('sdc-hidden');
			},

			maskSecurityCode: function() {
				chIframe.mask('securityCode');
				let unmaskButton = this.getSecurityUnmaskButton();
				let maskButton = this.getSecurityMaskButton();
				unmaskButton.removeClass('sdc-hidden');
				maskButton.addClass('sdc-hidden');
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
				this.showError($t('Card capture failure. Please try again.'));
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

			beginTokenizationFlow: function() {
                if (!this.paymentSessionData || !this.paymentSessionData["sessionId"]) {
                    this.showError($t('Payment session not established. Please try again.'));
                    return;
                }

                const sessionId = this.paymentSessionData["sessionId"];

				// Callbacks
				const validateCb = (response) => { return this.validateTokenizeResponse(response); };
				const successCb = () => { this.tokenizeSuccess(); };
				const errorCb = (message) => { this.tokenizeFailure(message); };
				const completeCB = () => { this.endAsyncFlow(); };

				// Data
				const errorMsg = this.tokenizeErrorMsg;
				const customerId = this.tokenConfig.customer_id;
				const storeUrl = this.tokenConfig.storeUrl;
				const tokenizeUrl = this.tokenizeUrl;
				const websiteId = this.tokenConfig.website_id;

				this.beginAsyncFlow();
				$.post({
					cache: false,
					url: storeUrl.concat(tokenizeUrl).concat("?session_id=").concat(sessionId).concat("&customer_id=").concat(customerId).concat("&website_id=").concat(websiteId),
					headers: {
						"Content-Type": "application/json",
						"X-Requested-With": "XMLHttpRequest"
					},
					success: function(response) {
						if (!validateCb(response)) errorCb(errorMsg);
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
				let responseObject = JSON.parse(response);
				let maskedCC = responseObject["maskedCC"];
				let expirationDate = responseObject["expirationDate"];

				return (typeof(maskedCC) !== "undefined" && typeof(expirationDate) !== "undefined");
			},

			tokenizeSuccess: function() {
				location.reload();
			},

			tokenizeFailure: function(message) {
				this.endAsyncFlow();
				this.showError(message);
				chIframe.resetIframe();
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
