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
	'mage/validation'
], function ($, ko, Component, setGiftCardAction, totals, messageList, sdcv2, quote, priceUtils, fullScreenLoader, errorProcessor) {
    'use strict';

	let config = structuredClone(window.checkoutConfig.payment.fiserv_commercehub);
	let valuelinkConfig = structuredClone(window.checkoutConfig.payment.fiserv_payments.fiserv_valuelink);

	config.formConfig = valuelinkConfig.valuelinkConfig;
		
	return Component.extend({
		defaults: {
			template: 'Fiserv_Payments/payment/commercehub/valuelink_form'
		},

		valuelinkBalanceUrl: "fiserv/valuelink/getvaluelinkbalance",
		formKey: undefined,
		isFormValid: false,

		/** @inheritdoc */
		initObservable: function () {
			this._super();

			return this;
		},

		initialize: function ()
		{
			this._super();
		
			if (this.isValuelinkEnabled())
			{
				this.formKey = sdcv2.initializeForm(
					config,
					() => { this.endIframeFlow(); },
					(valid) => { this.formValidHandler(valid); },
					(brand) => { /*DO NOTHING*/ },
					(data) => { this.fieldValidityHandler(data); },
					(data) => { this.fieldFocusHandler(data); }
				);
			}

			return this;
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
			return valuelinkConfig.isActive;
		},

		/**
		 * Get Valuelink title
		 d*/
		getValuelinkTitle: function() 
		{
			return valuelinkConfig.valuelink_title;
		},

		/**
		* Set gift card.
		*/
		setGiftCard: function () 
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
				this.showErrorMessage("Gift card has no balance.");	
				return;
			}

			setGiftCardAction(sessionId, balance);
			
			sdcv2.resetIframe(this.formKey);	
		},

		showErrorMessage: function(message)
		{
			messageList.clear();
			messageList.addErrorMessage({ "message" : message });
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
				window.checkoutConfig.payment.fiserv_payments["storeUrl"], 
				(data) => { this.balanceInquirySuccess(data); }, 
				(err) => { this.balanceInquiryFailure(err); });
		},

		checkBalanceAndSetCardCb: function(sessionId)
		{
			this.cardCaptureSuccess(sessionId);
			this.startIframeFlow();
			this.getValuelinkBalance(
				sessionId,
				window.checkoutConfig.payment.fiserv_payments["storeUrl"],
				(data) => { this.balanceInquirySuccess(data); this.setGiftCard(); },
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

		resetFormPanel: function()
		{
			this.hideCardInfoPanel();
			this.getBalanceInput().val('');
			this.getCardBalanceInfo().text('');
			this.getSessionIdInput().val('');
			this.getBalanceButton().prop('disabled', true);
			this.getApplyButton().prop('disabled', true);
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
			sdcv2.resetIframe(this.formKey);
			this.endIframeFlow();
			this.showErrorMessage(err);
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
					this.formKey,
					window.checkoutConfig.payment.fiserv_payments['storeUrl'],
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
			sdcv2.resetIframe(this.formKey);
		},

		toggleValuelinkForm: function()
		{
			if (!this.isContainerActive())
			{
				this.createGiftCardForm();
			} else
			{
				this.destroyGiftCardForm();
				this.formValidHandler(false);
			}
		},

		activateValuelinkForm: function ()
		{
			if (!this.isContainerActive())
			{
				this.createGiftCardForm();
			}
		},

		deactivateValuelinkForm: function ()
		{
			if (this.isContainerActive())
			{
				this.destroyGiftCardForm();
			}
		},

		createGiftCardForm: function()
		{
			let iframePromise = new Promise((resolve, reject) => {
				this.startIframeFlow();
				sdcv2.instantiateIframe(
					this.formKey,
					"GIFT",
					resolve,
					reject
				)
			});
			iframePromise.then((data) => {

			}).catch((error) =>{
				this.iframeLoadFailure(error); 
			})
		},

		destroyGiftCardForm: function()
		{
			sdcv2.destroyIframe(this.formKey);
		},

		isContainerActive: function()
		{
			return this.getContainer().hasClass("_active");
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

		startIframeFlow: function ()
		{
			fullScreenLoader.startLoader();
		},

		endIframeFlow: function ()
		{
			fullScreenLoader.stopLoader();
		},

		iframeLoadFailure: function (err)
		{
			errorProcessor.process("Unable to load gift card form. Please try again later.", messageList);
			this.endIframeFlow();
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
				  return config.invalidFields["cardNumber"];
				case "securityCode":
				  return config.invalidFields["securityCode"];
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
});
