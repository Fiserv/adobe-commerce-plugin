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
		config: {},
		iframeReadyCallback: undefined,
		iframeValidCallback: undefined,
		cardBrandChangeCallback: undefined,
		fieldValidityHandler: undefined,
		fieldFocusHandler: undefined,
		sdcv2Form: undefined,
		credentials: undefined,

		initialize: function (config, iframeReadyCallback, iframeValidCallback, cardBrandChangeCallback, fieldValidityHandler, fieldFocusHandler) {
			this.config = config;
			this.iframeReadyCallback = iframeReadyCallback;
			this.iframeValidCallback = iframeValidCallback;
			this.cardBrandChangeCallback = cardBrandChangeCallback;
			this.fieldValidityHandler = fieldValidityHandler;
			this.fieldFocusHandler = fieldFocusHandler;
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
			loadSuccessCb, 
			loadErrorCb
		) {
			let formConfig = this.buildFormConfig();
						
			window.fiserv.commercehub.createPaymentForm(formConfig)
				.then((next) => { 
					this.sdcv2Form = next; 
					this.iframeReadyCallback(); 
					loadSuccessCb();
				})
				.catch((data) => {
					console.log(data);
					loadErrorCb(data);
				});

		},

		submitCardForm: function (
			storeUrl,
			runSuccessCb, 
			runErrorCb
		) {
			if (typeof(this.sdcv2Form) !== "undefined") {
				let promise = new Promise((resolve, reject) => {
					this.getChCredentials(storeUrl, resolve, reject);	
				});

				promise.then((data) => {
					let submitConfig = this.buildFormSubmitPayload(data);
					this.sdcv2Form.submit(submitConfig)
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

		buildFormSubmitPayload: function(data) 
		{
			let payload = {
				"apiKey" : this.config[this.configApiKeyKey],
				"accessToken" : data[this.accessTokenKey],
				"createToken" : false,
				"publicKey" : data[this.publicTokenKey],
				"keyId" : data[this.keyIdKey],
				"merchantId" : this.config[this.configMerchantIdKey],
				"terminalId" : this.config[this.configTerminalIdKey]
			};

			return payload;
		},

		buildFormConfig: function (data) {
			let formConfig = {
				"data" : this.config[this.formConfigKey], 
				"hooks" : {
					"onFormValid" : () => { this.iframeValidCallback(true);  },
					"onFormNoLongerValid" : () => { this.iframeValidCallback(false);  },
					"onCardBrandChange" : (data) => { this.cardBrandChangeCallback(data); },
					"onFieldValidityChange" : (data) => { this.fieldValidityHandler(data); },
					"onFocus" : (data) => { this.fieldFocusHandler(data); },
					"onLostFocus" : (data) => { this.fieldFocusHandler(data); }
				} 

			}; 
			formConfig["data"]["environment"] =  this.config[this.environmentKey];
			// formConfig["data"]["supportedCardBrands"] = [];

			console.log(formConfig);

			return formConfig;
		},

		destroyIframe: function () {
			if (typeof(this.sdcv2Form) !== "undefined")
			{
				this.sdcv2Form.destroy();
			}
		},

		resetIframe: function () {
			if (typeof(this.sdcv2Form) !== "undefined")
			{
				this.sdcv2Form.reset();
			}
		}
	};
});
