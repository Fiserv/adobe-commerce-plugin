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
        sdkInitialized: false,

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
            formData.contextId = 'fiserv-form-' + idx + '-' + Date.now();
            formData.fiservContext = undefined;
            formData.sdkInitialized = false;
		
			this.forms[idx] = formData;

			return idx;
		},

		initAdapter: async function(config)
		{
			let promise = new Promise((resolve, reject) => {
				this.getChCredentials(config.storeUrl, resolve, reject);
			});

            promise.then((data) => {
                this.initSdk(config, data);
            })
                .catch((data) => {
                    console.log("WHOOPS something went wrong: ", data);
                });

		},

        initSdk: async function(config, creds, contextId)
        {
            const initPayload = {
                cspNonce: (typeof crypto !== 'undefined' && crypto.randomUUID) ? crypto.randomUUID() : undefined,
                environment: config.environment,
                accessToken: creds[this.accessTokenKey],
                apiKey: config.apiKey,
                merchantId: config.merchantId,
                publicKey: creds[this.publicTokenKey],
                keyId: creds[this.keyIdKey],
                terminalId: config[this.configTerminalIdKey],
                sessionId: creds[this.sessionIdKey]
            };

            // SDK v4: contextId is an optional field in the init payload.
            // When provided, creates a scoped context identified by that id.
            if (contextId) {
                initPayload.contextId = contextId;
            }

            await window.fiserv.init(initPayload);
            return contextId || null;
        },

		getChCredentials: function (storeUrl, successCb, errorCb) {
			let validateCb = this.validateCredentialsResponse.bind(this);
			let parseResponseCb = this.parseChCredentialsResponse.bind(this);
			let errorMsg = this.credentialsErrorMsg;

			$.ajax({
				url: storeUrl + this.credentialsUrl,
				cache: false,
				dataType: 'json',
				type: "POST",
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

            const ensureSdkReady = () => {
                if (formData.sdkInitialized) {
                    return Promise.resolve();
                }
                const storeUrl = formData.config.storeUrl ||
                    (window.checkoutConfig && window.checkoutConfig.payment &&
                        window.checkoutConfig.payment.fiserv_payments &&
                        window.checkoutConfig.payment.fiserv_payments.storeUrl) || '';
                return new Promise((resolve, reject) => {
                    this.getChCredentials(storeUrl, resolve, reject);
                }).then(async (creds) => {
                    formData.credentials = creds;
                    formData.fiservContext = await this.initSdk(
                        formData.config,
                        creds,
                        formData.contextId
                    );
                    formData.sdkInitialized = true;
                });
            };

            ensureSdkReady()
                .then(() => {
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
                })
                .catch((err) => {
                    console.log('[ch-adapter2] SDK init failure: ', err);
                    loadErrorCb(err);
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

			// SDK v4: retrieve the scoped context instance and attach it to the
			// paymentFields config. Components expect a context object, not a string.
			if (formData.contextId && window.fiserv) {
				const ctx = typeof window.fiserv.context === 'function'
					? window.fiserv.context(formData.contextId)
					: (window.fiserv.FiservContext && window.fiserv.FiservContext.getInstance)
						? window.fiserv.FiservContext.getInstance(formData.contextId)
						: undefined;
				if (ctx) {
					formConfig.context = ctx;
				}
			}

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
