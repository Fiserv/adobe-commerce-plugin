/*browser:true*/
/*global define*/
define([
	'jquery',
	'SDCv2Library',
], function (
	$,
	sdcv2,
) {
	'use strict';

	// chAdapter requires:
	// 1. Access token generated during initial credentials request
	// 2. CommerceHub API Key
	// 3. Form config with
	// 		a. CommerceHub MerchantID
	//		b. Public Key generated during initial credentials request
	//		c. Symmetric Encryption Algorithm generated during initial credentials request
	return {
		credentialsErrorMsg: "Oops! Something went wrong...",	
		credentialsUrl: "fiserv/commercehub/getcredentials",
		clientScriptId: "commercehub",
		configApiKeyKey: "apiKey",
		configMerchantIdKey: "merchantId",
		configTerminalIdKey: "terminalId",
		configEncryptionAlgoKey: "asymmetricEncryptionAlgorithm",
		formConfigKey: "formConfig",
		environmentKey: "environment",
		credentialsKey: "ch_credentials",
		keyIdKey: "keyId",
		accessTokenKey: "accessToken",
		publicTokenKey: "publicKey",
		sessionIdKey: "sessionId",
		encryptionAlgoKey: "symmetricEncryptionAlgorithm",
		prodEnv: "PROD",
		certEnv: "CERT",
		forms: {},

		initializeForm: function (config, iframeReadyCallback, iframeValidCallback, cardBrandChangeCallback, fieldValidityHandler, fieldFocusHandler) {
			let idx = Object.keys(this.forms).length + 1;

			let formData = {};
			formData.config = config;
			
			formData.config = config;
			formData.iframeReadyCallback = iframeReadyCallback;
			formData.iframeValidCallback = iframeValidCallback;
			formData.cardBrandChangeCallback = cardBrandChangeCallback;
			formData.fieldValidityHandler = fieldValidityHandler;
			formData.fieldFocusHandler = fieldFocusHandler;
		
			this.forms[idx] = formData;

			return idx;
		},

		getChCredentials: function (storeUrl, successCb, errorCb) {
			let validateCb = this.validateCredentialsResponse.bind(this);
			let parseResponseCb = this.parseChCredentialsResponse.bind(this);
			let errorMsg = this.credentialsErrorMsg;

			$.ajax({
				url: storeUrl + this.credentialsUrl,
				cache: false,
				dataType: 'json',
				type: "GET",
				success: function(response) {
					if (!validateCb(response)) {
						errorCb(errorMsg)
					}
					console.log(response);
					successCb(parseResponseCb(response));
				},
				error: function(err) {
					errorCb(errorMsg)
					console.log(err);
				}
			});
		},

		validateCredentialsResponse: function (response) {
			let credArray = response[this.credentialsKey];
			if (credArray === undefined) {
				return false;
			}
			if (credArray[this.accessTokenKey] === undefined) {
				return false;
			}
			if (credArray[this.publicTokenKey] === undefined) {
				return false;
			}
			if (credArray[this.sessionIdKey] === undefined) {
				return false;
			}
			if (credArray[this.encryptionAlgoKey] === undefined) {
				return false;
			}

			return true;
		},


		parseChCredentialsResponse: function (response) {
			return response[this.credentialsKey];
		},

		/**
		 * Instantiates CommerceHub iframe
		 * from provide script element
		 */
		/**
		 * Instantiates CommerceHub iframe
		 * from provide script element
		 */
		instantiateIframe: function (
			formKey,
			paymentMethod,
			loadSuccessCb, 
			loadErrorCb
		) {
			let formData = undefined;
			try {
				formData = this.getSdcForm(formKey);
			
			} catch(err)
			{
				loadErrorCb(err);
				return;
			}
			
			let formConfig = this.buildFormConfig(formData, paymentMethod);
						
			window.fiserv.components.paymentFields(formConfig)
				.then((next) => { 
					formData.sdcv2Form = next; 
					formData.iframeReadyCallback(); 
					loadSuccessCb();
				})
				.catch((data) => {
					console.log(data);
					loadErrorCb(data);
				});

		},

		submitCardForm: function (
			formKey,
			storeUrl,
			runSuccessCb, 
			runErrorCb
		) {
			let formData = this.getSdcForm(formKey);

			if (typeof(formData.sdcv2Form) !== "undefined") {
				let promise = new Promise((resolve, reject) => {
					this.getChCredentials(storeUrl, resolve, reject);	
				});

				promise.then((data) => {
					let submitConfig = this.buildFormSubmitPayload(formData.config, data);
					formData.sdcv2Form.submit(submitConfig)
						.then((next) => { 
							let sessionId = data[this.sessionIdKey];
							runSuccessCb(sessionId); 
						})
						.catch((data) => { console.log(data); runErrorCb(); });
				})
				.catch((data) => {
					runErrorCb(data);
				});
			};
		},

		buildFormSubmitPayload: function(config, data) 
		{
			let payload = {
				"apiKey" : config[this.configApiKeyKey],
				"accessToken" : data[this.accessTokenKey],
				"createToken" : false,
				"publicKey" : data[this.publicTokenKey],
				"keyId" : data[this.keyIdKey],
				"merchantId" : config[this.configMerchantIdKey],
				"terminalId" : config[this.configTerminalIdKey]
			};

			return payload;
		},

		buildFormConfig: function (formData, paymentMethod) {
			let formConfig = {
				"data" : formData.config[this.formConfigKey], 
				"hooks" : {
					"onFormValid" : () => { formData.iframeValidCallback(true);  },
					"onFormNoLongerValid" : () => { formData.iframeValidCallback(false);  },
					"onCardBrandChange" : (data) => { formData.cardBrandChangeCallback(data); },
					"onFieldValidityChange" : (data) => { formData.fieldValidityHandler(data); },
					"onFocus" : (data) => { formData.fieldFocusHandler(data); },
					"onLostFocus" : (data) => { formData.fieldFocusHandler(data); }
				} 

			}; 
			formConfig["data"]["environment"] =  formData.config[this.environmentKey];
			formConfig["data"]["paymentMethod"] = paymentMethod;
			// formConfig["data"]["supportedCardBrands"] = [];

			return formConfig;
		},

		destroyIframe: function (formKey) {
			let formData = this.getSdcForm(formKey);

			if (typeof(formData.sdcv2Form) !== "undefined")
			{
				formData.sdcv2Form.destroy();
			}
		},

		resetIframe: function (formKey) {
			let formData = this.getSdcForm(formKey);

			if (typeof(formData.sdcv2Form) !== "undefined")
			{
				formData.sdcv2Form.reset();
			}
		},

		getSdcForm: function (formId)
		{
			let formData = this.forms[formId];
			if (typeof(formData) === "undefined")
			{
				throw new Error("SDC Form not form with id: " + formId + " not found");
			}

			return formData;
		}
	};
});
