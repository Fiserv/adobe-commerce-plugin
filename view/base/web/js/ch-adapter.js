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
		config: {},
		iframeReadyCallback: undefined,
		iframeValidCallback: undefined,
		cardBrandChangeCallback: undefined,
		fieldValidityHandler: undefined,
		fieldFocusHandler: undefined,
		sdcv2Form: undefined,
		credentials: undefined,

		initialize(config, iframeReadyCallback, iframeValidCallback, cardBrandChangeCallback, fieldValidityHandler, fieldFocusHandler) {
			this.config = config;
			this.iframeReadyCallback = iframeReadyCallback;
			this.iframeValidCallback = iframeValidCallback;
			this.cardBrandChangeCallback = cardBrandChangeCallback;
			this.fieldValidityHandler = fieldValidityHandler;
			this.fieldFocusHandler = fieldFocusHandler;
		},

		async initSdk(config, creds) {
			await globalThis.fiserv.init({
				cspNonce: crypto.randomUUID(),
				environment: config.environment,
				accessToken: creds[this.accessTokenKey],
				apiKey: config.apiKey,
				merchantId: config.merchantId,
				publicKey: creds[this.publicTokenKey],
				keyId: creds[this.keyIdKey],
				terminalId: config[this.configTerminalIdKey],
				sessionId: creds[this.sessionIdKey],
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
			loadSuccessCallback,
			loadErrorCallback,
			configData,
		) {
			let formConfig = this.buildFormConfig();

			if (configData) {
				formConfig = { ...configData, ...formConfig };
			}

			globalThis.fiserv.components.paymentFields(formConfig)
				.then((next) => {
					this.sdcv2Form = next;
					this.iframeReadyCallback();
					loadSuccessCallback();
				})
				.catch((error) => {
					console.log(error);
					loadErrorCallback(error);
				});
		},

		/**
         * Load PayPal SDK
         * @param {Object} options - { customerId, intent }
         * @returns {Promise<Object>} PayPal component instance
         */
		async loadPayPalComponent(options) {
			if (!globalThis.fiserv || typeof globalThis.fiserv.components.paypal !== 'function') {
				throw new Error('Fiserv SDK not loaded or window.fiserv.components.paypal not available');
			}
			this.paypalComponent = await globalThis.fiserv.components.paypal(options);

			return this.paypalComponent;
		},

		submitCardForm(
			storeUrl,
			runSuccessCallback,
			runErrorCallback,
			creds,
		) {
			if (this.sdcv2Form !== undefined) {
				if (creds === undefined) {
					const promise = new Promise((resolve, reject) => {
						this.getChCredentials(storeUrl, resolve, reject);
					});

					promise.then((data) => {
						const submitConfig = this.buildFormSubmitPayload(data);

						this.sdcv2Form.submit(submitConfig)
							.then((next) => {
								const sessionId = data[this.sessionIdKey];

								runSuccessCallback(sessionId);
							})
							.catch((error) => { console.log(error); runErrorCallback(); });
					})
						.catch((error) => {
							runErrorCallback(error);
						});
				} else {
					const submitConfig = this.buildFormSubmitPayload(creds);

					this.sdcv2Form.submit(submitConfig)
						.then((next) => {
							const sessionId = creds[this.sessionIdKey];

							runSuccessCallback(sessionId);
						})
						.catch((error) => { console.log(error); runErrorCallback(); });
				}
			}
		},

		unmask(
			field,
		) {
			this.sdcv2Form.mask(field, false);
		},

		mask(
			field,
		) {
			this.sdcv2Form.mask(field, true);
		},

		buildFormSubmitPayload(data) {
			const payload = {
				apiKey: this.config[this.configApiKeyKey],
				accessToken: data[this.accessTokenKey],
				createToken: false,
				publicKey: data[this.publicTokenKey],
				keyId: data[this.keyIdKey],
				merchantId: this.config[this.configMerchantIdKey],
				terminalId: this.config[this.configTerminalIdKey],
			};

			return payload;
		},

		buildFormConfig(data) {
			const formConfig = {
				data: this.config[this.formConfigKey],
				hooks: {
					onFormValid: () => { this.iframeValidCallback(true); },
					onFormNoLongerValid: () => { this.iframeValidCallback(false); },
					onCardBrandChange: (data) => { this.cardBrandChangeCallback(data); },
					onFieldValidityChange: (data) => { this.fieldValidityHandler(data); },
					onFocus: (data) => { this.fieldFocusHandler(data); },
					onLostFocus: (data) => { this.fieldFocusHandler(data); },
				},

			};

			formConfig.data.environment = this.config[this.environmentKey];

			return formConfig;
		},

		destroyIframe() {
			if (this.sdcv2Form !== undefined) {
				this.sdcv2Form.destroy();
			}
		},

		resetIframe() {
			if (this.sdcv2Form !== undefined) {
				this.sdcv2Form.reset();
			}
		},
	};
});
