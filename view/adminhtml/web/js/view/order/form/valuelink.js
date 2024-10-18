define([
		'ko',
		'uiComponent',
		'Fiserv_Payments/js/ch-adapter2',
		'Magento_Ui/js/modal/alert',
		'Magento_Ui/js/lib/view/utils/dom-observer',
		'jquery',
		'domReady!'
	], function(
		ko,
		Component,
		sdcv2,
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

			valuelinkBalanceUrl: "fiserv/valuelink/getvaluelinkbalance",

			/**
			* @returns {exports.initialize}
			*/
			initialize: function (config) {
				if (typeof(config.valuelinkConfig) === "undefined") { 
					throw new Error('Required parameter valuelinkConfig not found!');
				}
				this.paymentConfig = config;
				this.paymentConfig.formConfig = this.paymentConfig.valuelinkConfig;

				// Intercept order.loadArea in order to add items block to reload list if necessary
				if(window.loadAreaReference === undefined) {
					window.loadAreaReference = order.loadArea;
				}
				order.loadArea = this.loadArea;

				if (this.isValuelinkEnabled())
				{
					this.code = 'fiserv_valuelink';

					if(window.valuelinkFormKey !== undefined) {
						this.disableEventListeners();
						this.destroyGiftCardForm();
					}
					
					window.valuelinkFormKey = sdcv2.initializeForm(
						this.paymentConfig,
						() => { this.endIframeFlow(); },
						(valid) => { this.formValidHandler(valid); },
						(brand) => { /*DO NOTHING*/ },
						(data) => { this.fieldValidityHandler(data); },
						(data) => { this.fieldFocusHandler(data); }
					);

					this.createGiftCardForm();
				}

				return this;
			},

			createGiftCardForm: function()
			{
				let iframePromise = new Promise((resolve, reject) => {
					sdcv2.instantiateIframe(
						window.valuelinkFormKey,
						"GIFT",
						resolve,
						reject
					)
				});
				iframePromise.then((data) => {
					
				}).catch((error) =>{
					this.iframeLoadFailure(error);
				})

				this.enableEventListeners();
			},

			destroyGiftCardForm: function()
			{
				sdcv2.destroyIframe(window.valuelinkFormKey);
			},

			getCode: function ()
			{
				return "fiserv_valuelink";
			},

			/**
			 * Is Valuelink enabled
			 */
			isValuelinkEnabled: function()
			{
				return this.paymentConfig.useValuelink;
			},

			getBalanceButton: function ()
			{
				return $('#' + this.getCode() + "-get-gift-card-balance");
			},

			getApplyButton: function ()
			{
				return $('#' + this.getCode() + "-apply-gift-card");
			},

			getContainer: function ()
			{
				return $('#' + this.getCode() + "-placer");
			},

			getCardInfoPanel: function ()
			{
				return $('#' + this.getCode() + "-card-info-panel");
			},

			getCardBalanceInfo: function ()
			{
				return $('#' + this.getCode() + "-card-balance-info");
			},

			getSessionIdInput: function()
			{
				return $('#' + this.getCode() + "-sessionId");
			},

			getBalanceInput: function()
			{
				return $('#' + this.getCode() + "-balance");
			},

			formValidHandler: function (valid)
			{
				this.isFormValid = valid;
				if (this.isFormValid)
				{
					this.getBalanceButton().prop('disabled', false);
					this.getApplyButton().prop('disabled', false);
				}
				else
				{
					this.resetFormPanel();
				}
			},

			resetFormPanel: function()
			{
				this.hideCardInfoPanel();
				this.getBalanceInput().val('');
				this.getCardBalanceInfo().text('');
				this.getSessionIdInput().val('');
				this.getBalanceButton().prop('disabled', true);
				this.getApplyButton().prop('disabled', true);
			},

			iframeLoadSuccess: function (data) { },

			iframeLoadFailure: function(message) {
				this.showError(message);
			},

			iframeRunFailure: function() {
				this.showError("Card capture failure. Please try again."); 
				this.resetFormPanel();
			},

			iframeValidHandler: function(valid) {
				this.iframeValid = valid;
			},

			startIframeFlow: function() {
				 $('body').trigger('processStart');
			},

			endIframeFlow: function() {
				 $('body').trigger('processStop');	
			},

			/**
			 * Set gift card.
			 */
			setValuelinkCard: function ()
			{
				if (!this.isFormValid)
				{
					this.formValidHandler(false);
					return;
				}

				let balance = this.getBalanceInput().val();
				let sessionId = this.getSessionIdInput().val();

				if (!sessionId)
				{
					this.captureCardForm((sessionId) => { this.checkBalanceAndSetCardCb(sessionId); });
					return;
				}

				if (!balance)
				{
					this.checkBalanceAndSetCardCb(sessionId);
					return;
				}

				if (parseFloat(balance) === 0)
				{
					this.showError("Gift card has no balance.");
					return;
				}
				
				this.setValuelinkCardAction(sessionId, balance);
			},

			setValuelinkCardAction: function (sessionId, balance)
			{
				let data = {};
				data["valuelink_add"] = sessionId;
				data["valuelink_balance"] = balance;
				order.loadArea(['totals', 'billing_method', 'items'], true, data);
			},

			loadArea: function (area, indicator, params, callback)
			{
				if(!area.includes('items') && params['order[shipping_method]'] !== undefined && $('#valuelink-added-cards-container').length !== 0)
				{
					area.push('items');
				}
				return window.loadAreaReference.apply(order, [area, indicator, params, callback]);
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

			/**
			 * Check balance.
			 */
			checkBalance: function ()
			{
				//if (this.validate()) {
				//    getGiftCardAction.check(this.giftCartCode());
				//}

				this.captureCardForm((sessionId) => { this.checkBalanceCaptureCb(sessionId); });
			},

			checkBalanceCaptureCb: function(sessionId)
			{
				this.cardCaptureSuccess(sessionId);
				this.startIframeFlow();
				this.getValuelinkBalance(
					sessionId,
					this.paymentConfig["storeUrl"],
					(data) => { this.balanceInquirySuccess(data); },
					(err) => { this.balanceInquiryFailure(err); });
			},

			checkBalanceAndSetCardCb: function(sessionId)
			{
				this.cardCaptureSuccess(sessionId);
				this.startIframeFlow();
				this.getValuelinkBalance(
					sessionId,
					this.paymentConfig["storeUrl"],
					(data) => { this.balanceInquirySuccess(data); this.setValuelinkCard(); },
					(err) => { this.balanceInquiryFailure(err); });
			},

			balanceInquirySuccess: function(data)
			{
				this.endIframeFlow();

				let balanceInquiryValidationResponse = this.validateBalanceInquiry(data);
				if (!balanceInquiryValidationResponse["isValid"])
				{
					return this.balanceInquiryFailure(balanceInquiryValidationResponse["message"]);
				}

				if (parseFloat(data.valuelink_balance.endingBalance) <= 0)
				{
					this.getApplyButton().prop("disabled", true);
				}

				this.getBalanceButton().prop("disabled", true);
				this.showCardInfoPanel(data.valuelink_balance.endingBalance, data.valuelink_balance.currency);
			},

			showCardInfoPanel: function(balance, currency)
			{
				this.getBalanceInput().val(balance);
				this.getCardBalanceInfo().text(this.formatBalanceAsCurrency(balance, currency));
				this.getCardInfoPanel().show();
			},

			hideCardInfoPanel: function()
			{
				this.getCardInfoPanel().hide();
			},

			formatBalanceAsCurrency: function(balance, currency)
			{
				let formatter = new Intl.NumberFormat('en-US', {
					style: 'currency',
					currency: currency
				});

				return formatter.format(balance);
			},

			balanceInquiryFailure: function(err)
			{
				sdcv2.resetIframe(window.valuelinkFormKey);
				this.endIframeFlow();
				this.showError(err);
			},

			validateBalanceInquiry: function(response)
			{
				let valid =
						typeof(response.valuelink_balance) !== "undefined" &&
						typeof(response.valuelink_balance.currency) !== "undefined" &&
						typeof(response.valuelink_balance.endingBalance) !== "undefined" &&
						typeof(response.valuelink_balance.responseMessage) !== "undefined" &&
						!isNaN(parseFloat(response.valuelink_balance.endingBalance)) &&
						response.valuelink_balance.responseMessage === "Approved";

				let message = typeof(response.valuelink_balance) !== "undefined" &&
						typeof(response.valuelink_balance.responseMessage) !== "undefined" ?
						response.valuelink_balance.responseMessage : "Error retreiving gift card balance.";

				// Remove this line one day with a propper message mapper...
				message = message === "Invalid SKU/EAN/SCV" ? "Invalid security code provided" : "Error retreiving gift card balance.";

				return { "isValid": valid, "message": message };
			},

			captureCardForm: function(successCb)
			{
				if (this.isFormValid === true)
				{
					this.startIframeFlow();
					sdcv2.submitCardForm(
						window.valuelinkFormKey,
						this.paymentConfig['storeUrl'],
						successCb,
						() => { this.cardCaptureFailure(); }
					)
				} else
				{
					console.log("Valuelink gift card form invalid");
				}
			},

			cardCaptureSuccess: function(sessionId)
			{
				this.endIframeFlow();
				this.getSessionIdInput().val(sessionId);
			},

			cardCaptureFailure: function()
			{
				this.endIframeFlow();
				sdcv2.resetIframe(window.valuelinkFormKey);
			},

			getValuelinkBalance: function (sessionId, storeUrl, successCb, errorCb)
			{
				$.ajax({
					url: storeUrl + this.valuelinkBalanceUrl + "?sessionId=" + sessionId,
					cache: false,
					dataType: 'json',
					type: "GET",
					success: function(response)
					{
						successCb(response);
					},
					error: function(err)
					{
						errorCb(err)
					}
				});
			},

			enableEventListeners: function ()
			{
				this.getBalanceButton().on('click', () => { this.checkBalance(); });
				this.getApplyButton().on('click', () => { this.setValuelinkCard(); });
			},

			disableEventListeners: function ()
			{
				this.getBalanceButton().off('click');
				this.getApplyButton().off('click');
			},

			getSdcFieldFrame: function(name) {
				switch(name)
				{
					case "cardNumber":
						return $('#valuelink-sdc-card-number-frame');
					case "securityCode":
						return $('#valuelink-sdc-security-code-frame');
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
						return $('#valuelink-sdc-card-number-invalid-message');
					case "securityCode":
						return $('#valuelink-sdc-security-code-invalid-message');
				}               

				return undefined;
			},              

			getSdcInvalidFieldMessageText: function(name) {
				switch(name)    
				{       
					case "cardNumber":
						return this.getInvalidFieldMessages()["cardNumber"];
					case "securityCode":
						return this.getInvalidFieldMessages()["securityCode"];
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
