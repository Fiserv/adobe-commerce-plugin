/* browser:true */
/* global define */
define([
	'jquery',
	'SDCv2Library',
], (
	$,
	sdcv2,
) => {
	'use strict';

	// chAdapter requires:
	// 1. Access token generated during initial credentials request
	// 2. CommerceHub API Key
	// 3. Form config with
	// 		a. CommerceHub MerchantID
	//		b. Public Key generated during initial credentials request
	//		c. Symmetric Encryption Algorithm generated during initial credentials request
	return {
		credentialsErrorMsg: 'Oops! Something went wrong...',
		credentialsUrl: 'fiserv/commercehub/getcredentials',
		clientScriptId: 'commercehub',
		configApiKeyKey: 'apiKey',
		configMerchantIdKey: 'merchantId',
		configTerminalIdKey: 'terminalId',
		configEncryptionAlgoKey: 'asymmetricEncryptionAlgorithm',
		formConfigKey: 'formConfig',
		environmentKey: 'environment',
		credentialsKey: 'ch_credentials',
		keyIdKey: 'keyId',
		accessTokenKey: 'accessToken',
		publicTokenKey: 'publicKey',
		sessionIdKey: 'sessionId',
		encryptionAlgoKey: 'symmetricEncryptionAlgorithm',
		prodEnv: 'PROD',
		certEnv: 'CERT',
		forms: {},

		initializeForm(config, iframeReadyCallback, iframeValidCallback, cardBrandChangeCallback, fieldValidityHandler, fieldFocusHandler) {
			const index = Object.keys(this.forms).length + 1;

			const formData = {};

			formData.config = config;

			formData.config = config;
			formData.iframeReadyCallback = iframeReadyCallback;
			formData.iframeValidCallback = iframeValidCallback;
			formData.cardBrandChangeCallback = cardBrandChangeCallback;
			formData.fieldValidityHandler = fieldValidityHandler;
			formData.fieldFocusHandler = fieldFocusHandler;

			this.forms[index] = formData;

			return index;
		},

		async initAdapter(config) {
			const promise = new Promise((resolve, reject) => {
				this.getChCredentials(config.storeUrl, resolve, reject);
			});

			promise.then((data) => {
				initSdk(config, data[this.accessTokenKey]);
			})
				.catch((error) => {
					console.log('WHOOPS something went wrong:', error);
				});
		},

		async initSdk(config, accessToken) {
			await globalThis.fiserv.init({
				environment: config.environment,
				accessToken,
				apiKey: config.apiKey,
				merchantId: config.merchantId,
			});
		},

		getChCredentials(storeUrl, successCallback, errorCallback) {
			const validateCallback = this.validateCredentialsResponse.bind(this);
			const parseResponseCallback = this.parseChCredentialsResponse.bind(this);
			const errorMessage = this.credentialsErrorMsg;

			$.ajax({
				url: storeUrl + this.credentialsUrl,
				cache: false,
				dataType: 'json',
				type: 'POST',
				success(response) {
					if (!validateCallback(response)) {
						errorCallback(errorMessage);
					}
					console.log(response);
					successCallback(parseResponseCallback(response));
				},
				error(error) {
					errorCallback(errorMessage);
					console.log(error);
				},
			});
		},

		validateCredentialsResponse(response) {
			const credArray = response[this.credentialsKey];

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

		parseChCredentialsResponse(response) {
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
		instantiateIframe(
			formKey,
			paymentMethod,
			loadSuccessCallback,
			loadErrorCallback,
		) {
			let formData;

			try {
				formData = this.getSdcForm(formKey);
			} catch (error) {
				loadErrorCallback(error);

				return;
			}

			const formConfig = this.buildFormConfig(formData, paymentMethod);

			globalThis.fiserv.components.paymentFields(formConfig)
				.then((next) => {
					formData.sdcv2Form = next;
					formData.iframeReadyCallback();
					loadSuccessCallback();
				})
				.catch((error) => {
					console.log(error);
					loadErrorCallback(error);
				});
		},

		submitCardForm(
			formKey,
			storeUrl,
			runSuccessCallback,
			runErrorCallback,
		) {
			const formData = this.getSdcForm(formKey);

			if (formData.sdcv2Form !== undefined) {
				const promise = new Promise((resolve, reject) => {
					this.getChCredentials(storeUrl, resolve, reject);
				});

				promise.then((data) => {
					const submitConfig = this.buildFormSubmitPayload(formData.config, data);

					formData.sdcv2Form.submit(submitConfig)
						.then((next) => {
							const sessionId = data[this.sessionIdKey];

							runSuccessCallback(sessionId);
						})
						.catch((error) => { console.log(error); runErrorCallback(); });
				})
					.catch((error) => {
						runErrorCallback(error);
					});
			}
		},

		buildFormSubmitPayload(config, data) {
			const payload = {
				apiKey: config[this.configApiKeyKey],
				accessToken: data[this.accessTokenKey],
				createToken: false,
				publicKey: data[this.publicTokenKey],
				keyId: data[this.keyIdKey],
				merchantId: config[this.configMerchantIdKey],
				terminalId: config[this.configTerminalIdKey],
			};

			return payload;
		},

		buildFormConfig(formData, paymentMethod) {
			const formConfig = {
				data: formData.config[this.formConfigKey],
				hooks: {
					onFormValid: () => { formData.iframeValidCallback(true); },
					onFormNoLongerValid: () => { formData.iframeValidCallback(false); },
					onCardBrandChange: (data) => { formData.cardBrandChangeCallback(data); },
					onFieldValidityChange: (data) => { formData.fieldValidityHandler(data); },
					onFocus: (data) => { formData.fieldFocusHandler(data); },
					onLostFocus: (data) => { formData.fieldFocusHandler(data); },
				},

			};

			formConfig.data.environment = formData.config[this.environmentKey];
			formConfig.data.paymentMethod = paymentMethod;
			// formConfig["data"]["supportedCardBrands"] = [];

			return formConfig;
		},

		destroyIframe(formKey) {
			const formData = this.getSdcForm(formKey);

			if (formData.sdcv2Form !== undefined) {
				formData.sdcv2Form.destroy();
			}
		},

		resetIframe(formKey) {
			const formData = this.getSdcForm(formKey);

			if (formData.sdcv2Form !== undefined) {
				formData.sdcv2Form.reset();
			}
		},

		getSdcForm(formId) {
			const formData = this.forms[formId];

			if (formData === undefined) {
				throw new TypeError(`SDC Form not form with id: ${formId} not found`);
			}

			return formData;
		},
	};
});
