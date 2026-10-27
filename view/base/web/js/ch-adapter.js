/*browser:true*/
/*global define*/
define([
	'jquery',
	'SDCv2Library',
	'mage/translate'
], function (
	$,
	sdcv2,
	$t
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
		credentialsUrl: "fiserv/commercehub/createsession",
		// Legacy /credentials endpoint (payments-vas/v1/security/credentials), same one used by
		// the Valuelink gift card lane. Used for vaulted-card CVV checkouts: CommerceHub rejects
		// source.sessionId when paired with sourceType PaymentToken via the newer /sessions/create
		// (session-intent) flow, but accepts it when the session comes from this legacy endpoint.
		vaultCredentialsUrl: "fiserv/commercehub/getcredentials",
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
		sdkDataKey: "sdkData",
		config: {},
		iframeReadyCallback: undefined,
		iframeValidCallback: undefined,
		cardBrandChangeCallback: undefined,
		fieldValidityHandler: undefined,
		fieldFocusHandler: undefined,
		fieldValueChangeHandler: undefined,
		sdcv2Form: undefined,
		credentials: undefined,
		mountCredentials: undefined,
		contextId: undefined,

		initialize: function (config, iframeReadyCallback, iframeValidCallback, cardBrandChangeCallback, fieldValidityHandler, fieldFocusHandler, fieldValueChangeHandler) {
			this.config = config;
			this.iframeReadyCallback = iframeReadyCallback;
			this.iframeValidCallback = iframeValidCallback;
			this.cardBrandChangeCallback = cardBrandChangeCallback;
			this.fieldValidityHandler = fieldValidityHandler;
			this.fieldFocusHandler = fieldFocusHandler;
			this.fieldValueChangeHandler = fieldValueChangeHandler;
		},

		/** SDK v4: Assign a scoped FiservContext id for paymentFields components */
		setContextId: function (contextId) {
			this.contextId = contextId;
		},

		initSdk: async function (config, creds, contextId) {
			if (contextId) {
				this.contextId = contextId;
			}

			this.credentials = creds;

			const initPayload = {
				cspNonce: crypto.randomUUID(),
				environment: config.environment,
				accessToken: creds[this.accessTokenKey],
				apiKey: config.apiKey,
				merchantId: config.merchantId,
				publicKey: creds[this.publicTokenKey],
				keyId: creds[this.keyIdKey],
				terminalId: config[this.configTerminalIdKey],
				sessionId: creds[this.sessionIdKey],
				sdkData: creds[this.sdkDataKey]
			};

			if (contextId) {
				initPayload.contextId = contextId;
			}

			await window.fiserv.init(initPayload);
		},

		getChCredentials: function (storeUrl, successCb, errorCb, url) {
			let validateCb = this.validateCredentialsResponse.bind(this);
			let parseResponseCb = this.parseChCredentialsResponse.bind(this);
			let errorMsg = this.credentialsErrorMsg;

			$.ajax({
				url: storeUrl + (url || this.credentialsUrl),
				cache: false,
				dataType: 'json',
				type: "POST",
				success: function (response) {
					if (!validateCb(response)) {
						errorCb(errorMsg);
						return;
					}

					successCb(parseResponseCb(response));
				},
				error: function (err) {
					errorCb(errorMsg);
					console.log(err);
				}
			});
		},

		/** Vaulted-card CVV flow: fetch credentials from the legacy /credentials endpoint. */
		getVaultCredentials: function (storeUrl, successCb, errorCb) {
			this.getChCredentials(storeUrl, successCb, errorCb, this.vaultCredentialsUrl);
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

		createBillingAddressComponent: async function (addressFields) {
			const addressOptions = {
				fields: addressFields
			};

			if (this.contextId && window.fiserv) {
				const ctx = window.fiserv.context(this.contextId);

				if (ctx) {
					addressOptions.context = ctx;
				}
			}

			return await window.fiserv.components.address(addressOptions);
		},

		/**
		 * Instantiates CommerceHub iframe
		 * from provided script element
		 */
		instantiateIframe: function (
			loadSuccessCb,
			loadErrorCb,
			configData = undefined
		) {
			let formConfig = this.buildFormConfig();

			if (configData) {
				formConfig = {
					...formConfig,
					...configData,
					data: {
						...(formConfig.data || {}),
						...((configData && configData.data) || {})
					}
				};
			}

			if (!formConfig.data) {
				formConfig.data = {};
			}

			if (!formConfig.data.environment) {
				formConfig.data.environment =
					this.config[this.environmentKey] ||
					(
						window.checkoutConfig &&
						window.checkoutConfig.payment &&
						window.checkoutConfig.payment.fiserv_payments ?
							window.checkoutConfig.payment.fiserv_payments.environment :
							undefined
					);
			}

			window.fiserv.components.paymentFields(formConfig)
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

		/**
		 * Load PayPal SDK
		 * @param {Object} options - { customerId, intent }
		 * @returns {Promise<Object>} PayPal component instance
		 */
		async loadPayPalComponent(options) {
			if (!window.fiserv || typeof window.fiserv.components.paypal !== 'function') {
				throw new Error($t('Fiserv SDK not loaded or window.fiserv.components.paypal not available'));
			}

			this.paypalComponent = await window.fiserv.components.paypal(options);

			return this.paypalComponent;
		},

		/**
		 * Load Paze SDK (Step 4 of Paze documentation)
		 * @param {Object} options - { displayName, cspNonce }
		 * @returns {Promise<Object>} Paze component instance
		 */
		async loadPazeComponent(options) {
			if (!window.fiserv || typeof window.fiserv.components.paze !== 'function') {
				throw new Error($t('Fiserv SDK not loaded or window.fiserv.components.paze not available'));
			}

			// Paze component initialization with displayName and optional cspNonce
			const pazeOptions = {
				displayName: options.displayName || 'My Site'
			};

			if (options.cspNonce) {
				pazeOptions.cspNonce = options.cspNonce;
			}

			console.log('[CH-Adapter] Loading Paze component with options:', pazeOptions);
			this.pazeComponent = await window.fiserv.components.paze(pazeOptions);

			return this.pazeComponent;
		},

		/**
		 * Load Cash App Pay SDK component
		 * @param {Object} options - { data: { button }, hooks: { onApprove, onCancel, onError } }
		 * @returns {Promise<Object>} Cash App component instance
		 */
		async loadCashAppComponent(options) {
			if (!window.fiserv || !window.fiserv.components || typeof window.fiserv.components.cashApp !== 'function') {
				throw new Error($t('Fiserv SDK not loaded or window.fiserv.components.cashApp not available'));
			}

			this.cashAppComponent = await window.fiserv.components.cashApp(options);

			return this.cashAppComponent;
		},

		submitIframeForm: function (
			runSuccessCb,
			runErrorCb,
			billingAddress = undefined
		) {
			if (!this.sdcv2Form) {
				throw new Error($t('Card capture form not found.'));
			}

			const submitConfig = billingAddress ? { billingAddress } : undefined;

			this.sdcv2Form.submit(submitConfig)
				.then((next) => {
					// Return the sessionId actually bound at init time (this.credentials),
					// never a separately/later-fetched one, so callers can't send a mismatched
					// sessionId that the CVV was never captured under.
					runSuccessCb(this.credentials && this.credentials[this.sessionIdKey]);
				})
				.catch((data) => {
					console.log(data);
					runErrorCb();
				});
		},

		getAchLegalText: async function (billingAddress) {
			if (this.sdcv2Form === undefined || typeof this.sdcv2Form.getAchLegalText !== 'function') {
				throw new Error($t('ACH payment form is not initialized.'));
			}

			const submitConfig = billingAddress ? { billingAddress } : undefined;
			const legalTextResponse = await this.sdcv2Form.getAchLegalText(submitConfig);

			if (!legalTextResponse.plainText) {
				throw new Error($t('ACH legal text not found'));
			}

			return legalTextResponse.plainText;
		},

		unmask: function (
			field
		) {
			this.sdcv2Form.mask(field, false);
		},

		mask: function (
			field
		) {
			this.sdcv2Form.mask(field, true);
		},

		buildFormSubmitPayload: function (data) {
			let payload = {
				"apiKey": this.config[this.configApiKeyKey],
				"accessToken": data[this.accessTokenKey],
				"createToken": false,
				"publicKey": data[this.publicTokenKey],
				"keyId": data[this.keyIdKey],
				"merchantId": this.config[this.configMerchantIdKey],
				"terminalId": this.config[this.configTerminalIdKey],
				"sdkData": data[this.sdkDataKey]
			};

			return payload;
		},

		buildFormConfig: function (data) {
			let formConfig = {
				"data": {
					...(this.config[this.formConfigKey] || {})
				},
				"hooks": {
					"onFormValid": () => {
						if (typeof this.iframeValidCallback === 'function') {
							this.iframeValidCallback(true);
						}
					},
					"onFormNoLongerValid": () => {
						if (typeof this.iframeValidCallback === 'function') {
							this.iframeValidCallback(false);
						}
					},
					"onCardBrandChange": (data) => {
						if (typeof this.cardBrandChangeCallback === 'function') {
							this.cardBrandChangeCallback(data);
						}
					},
					"onFieldValidityChange": (data) => {
						if (typeof this.fieldValidityHandler === 'function') {
							this.fieldValidityHandler(data);
						}
					},
					"onFocus": (data) => {
						if (typeof this.fieldFocusHandler === 'function') {
							this.fieldFocusHandler(data);
						}
					},
					"onLostFocus": (data) => {
						if (typeof this.fieldFocusHandler === 'function') {
							this.fieldFocusHandler(data);
						}
					},
					"onFieldValueChange": (data) => {
						if (typeof this.fieldValueChangeHandler === 'function') {
							this.fieldValueChangeHandler(data);
						}
					}
				}
			};

			formConfig["data"]["environment"] = this.config[this.environmentKey];

			// Retrieve the scoped context and attach it to the payment fields
			if (this.contextId && window.fiserv) {
				const ctx = window.fiserv.context(this.contextId);

				if (ctx) {
					formConfig.context = ctx;
				}
			}

			return formConfig;
		},

		destroyIframe: function () {
			if (typeof this.sdcv2Form !== "undefined") {
				this.sdcv2Form.destroy();
			}
		},

		resetIframe: function () {
			if (typeof this.sdcv2Form !== "undefined") {
				this.sdcv2Form.reset();
			}
		},

		getSurchargeEstimate: async function (billingAddress) {
			if (typeof this.sdcv2Form === "undefined") {
				throw new Error($t('Payment form not initialized.'));
			}

			return await this.sdcv2Form.getSurchargeEstimate({ billingAddress });
		}
	};
});
