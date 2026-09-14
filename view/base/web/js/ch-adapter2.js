/*browser:true*/
/*global define*/
define([
	'jquery',
	'SDCv2Library',
	'mage/translate',
], function (
	$,
	sdcv2,
	$t,
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
		credentialsUrl: "fiserv/valuelink/getcredentials",
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
				cspNonce: crypto.randomUUID(),
				environment: config.environment,
				accessToken: creds[this.accessTokenKey],
				apiKey: config.apiKey,
				merchantId: config.merchantId,
				publicKey: creds[this.publicTokenKey],
				keyId: creds[this.keyIdKey],
				terminalId: config[this.configTerminalIdKey],
				sessionId: creds[this.sessionIdKey]
			};

			if (contextId) {
				initPayload.contextId = contextId;
			}

			return await window.fiserv.init(initPayload);
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

			if (typeof(formData.sdcv2Form) === "undefined") {
				return;
			}

			const creds = formData.credentials;

			let submitConfig = this.buildFormSubmitPayload(formData.config, creds);
			formData.sdcv2Form.submit(submitConfig)
				.then((next) => {
					runSuccessCb(creds[this.sessionIdKey]);
				})
				.catch((data) => { console.log(data); runErrorCb(); });
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
					"onFormValid" : () => { formData.iframeValidCallback(true); },
					"onFormNoLongerValid" : () => { formData.iframeValidCallback(false); },
					"onCardBrandChange" : (data) => { formData.cardBrandChangeCallback(data); },
					"onFieldValidityChange" : (data) => { formData.fieldValidityHandler(data); },
					"onFocus" : (data) => { formData.fieldFocusHandler(data); },
					"onLostFocus" : (data) => { formData.fieldFocusHandler(data); }
				} 
			};
			formConfig["data"]["environment"] = formData.config[this.environmentKey];
			formConfig["data"]["paymentMethod"] = paymentMethod;

			// SDK v4: prefer the context object returned by init() for THIS
			// form; window.fiserv.context(contextId) is only a fallback for
			// callers that never captured the returned context.
			if (formData.contextId && window.fiserv) {
				const ctx = formData.fiservContext || window.fiserv.context(formData.contextId);
				if (ctx) { formConfig.context = ctx; }
			}

			return formConfig;
		},

		/**
		 * VAS PaymentSession IDs are single-reference: once a sessionId has
		 * been used for ANY CommerceHub call (card-capture, balance-inquiry,
		 * or a charge), it cannot be referenced again - a follow-up call
		 * against the same sessionId is rejected with
		 * "source.sessionId: Invalid or Missing Field Data".
		 *
		 * A mounted hosted-fields form is bound to whichever session was
		 * active on its contextId at init() time; simply passing a
		 * different session's credentials into submit() does NOT change
		 * that - the form keeps submitting against its original session.
		 *
		 * This method re-initializes the SAME contextId with a brand new
		 * (still-unused) session BEFORE resubmitting, which rebinds the
		 * already-mounted iframe to the new session in place - without
		 * destroying/remounting the hosted fields, so the shopper does not
		 * have to re-enter their card details.
		 */
		rebindSessionAndSubmit: function (formKey, storeUrl, runSuccessCb, runErrorCb) {
			let formData;
			try {
				formData = this.getSdcForm(formKey);
			} catch (err) {
				runErrorCb(err);
				return;
			}

			if (typeof(formData.sdcv2Form) === "undefined") {
				runErrorCb();
				return;
			}

			this.getChCredentials(
				storeUrl,
				async (creds) => {
					try {
						formData.fiservContext = await this.initSdk(formData.config, creds, formData.contextId);
						formData.credentials = creds;

						let submitConfig = this.buildFormSubmitPayload(formData.config, creds);
						formData.sdcv2Form.submit(submitConfig)
							.then(() => { runSuccessCb(creds[this.sessionIdKey]); })
							.catch((data) => { console.log(data); runErrorCb(data); });
					} catch (err) {
						runErrorCb(err);
					}
				},
				(err) => { runErrorCb(err); }
			);
		},

		destroyIframe: function (formKey) {
			let formData = this.getSdcForm(formKey);

			if (typeof(formData.sdcv2Form) !== "undefined")
			{
				formData.sdcv2Form.destroy();
			}

			formData.sdcv2Form = undefined;
			formData.sdkInitialized = false;
			formData.credentials = undefined;
		},

		resetIframe: function (formKey) {
			this.destroyIframe(formKey);
		},

		getSdcForm: function (formId)
		{
			let formData = this.forms[formId];
			if (typeof(formData) === "undefined")
			{
				throw new Error($t('SDC Form not form with id: %1 not found').replace('%1', formId));
			}

			return formData;
		}
	};
});
