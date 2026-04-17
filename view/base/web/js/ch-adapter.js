/*browser:true*/
/*global define*/
define([
	'jquery',
	'SDCv2Library'
], function (
	$,
	sdcv2
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
		fieldValueChangeHandler: undefined,
		sdcv2Form: undefined,
		credentials: undefined,

		initialize: function (config, iframeReadyCallback, iframeValidCallback, cardBrandChangeCallback, fieldValidityHandler, fieldFocusHandler, fieldValueChangeHandler) {
			this.config = config;
			this.iframeReadyCallback = iframeReadyCallback;
			this.iframeValidCallback = iframeValidCallback;
			this.cardBrandChangeCallback = cardBrandChangeCallback;
			this.fieldValidityHandler = fieldValidityHandler;
			this.fieldFocusHandler = fieldFocusHandler;
			this.fieldValueChangeHandler = fieldValueChangeHandler;
		},

  		initSdk: async function(config, creds)
		{
			await window.fiserv.init({
  				cspNonce: crypto.randomUUID(),
				environment: config.environment,
				accessToken: creds[this.accessTokenKey], 
				apiKey: config.apiKey,
				merchantId: config.merchantId,
				publicKey: creds[this.publicTokenKey],
				keyId: creds[this.keyIdKey],
				terminalId: config[this.configTerminalIdKey],
				sessionId: creds[this.sessionIdKey]
			}); 
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

		createBillingAddressComponent: async function (billingAddress) {
			if (!billingAddress || typeof billingAddress !== 'object') {
				return null;
			}

			let containerId = 'fiserv-ach-billing-address-container';
			let container = document.getElementById(containerId);

			if (!container) {
				container = document.createElement('div');
				container.id = containerId;
				container.style.display = 'none';
				document.body.append(container);
			}

			container.innerHTML = '';

			let address = billingAddress.address || {};
			let fieldMap = {
				firstName: billingAddress.firstName || '',
				lastName: billingAddress.lastName || '',
				street: address.street || '',
				city: address.city || '',
				stateOrProvince: address.stateOrProvince || '',
				postalCode: address.postalCode || '',
				country: address.country || '',
			};

			let addressFields = {};

			for (let key in fieldMap) {
				let inputId = 'fiserv-ach-billing-' + key;
				let input = document.createElement('input');
				input.type = 'text';
				input.id = inputId;
				input.value = fieldMap[key];
				container.append(input);
				addressFields[key] = { elementId: inputId };
			}

			return await window.fiserv.components.address({ fields: addressFields });
		},

		/**
		 * Instantiates CommerceHub iframe
		 * from provide script element
		 */
		instantiateIframe: function (
			loadSuccessCb, 
			loadErrorCb,
			configData = undefined,
			billingAddress = undefined
		) {
			let formConfig = this.buildFormConfig();

			if (configData) {
				let configDataToMerge = { ...configData };
				delete configDataToMerge.data;
				formConfig = {
					...formConfig,
					...configDataToMerge,
					data: {
						...(formConfig.data || {}),
						...((configData && typeof configData.data === 'object' && configData.data) || {}),
					},
				};
			}

			if (!formConfig.data || typeof formConfig.data !== 'object') {
				formConfig.data = {};
			}

			if (!formConfig.data.environment) {
				formConfig.data.environment =
					this.config[this.environmentKey] ||
					(window.checkoutConfig && window.checkoutConfig.payment && window.checkoutConfig.payment.fiserv_payments ?
						window.checkoutConfig.payment.fiserv_payments.environment :
						undefined);
			}

			if (!formConfig.data) {
				formConfig.data = {};
			}

			if (!formConfig.data.environment) {
				formConfig.data.environment =
					this.config[this.environmentKey] ||
					(window.checkoutConfig && window.checkoutConfig.payment && window.checkoutConfig.payment.fiserv_payments ?
						window.checkoutConfig.payment.fiserv_payments.environment :
						undefined);
			}

			let initPromise = Promise.resolve();

			if (billingAddress && typeof billingAddress === 'object') {
				initPromise = new Promise((resolve, reject) => {
					let storeUrl = window.checkoutConfig && window.checkoutConfig.payment && window.checkoutConfig.payment.fiserv_payments ?
						window.checkoutConfig.payment.fiserv_payments.storeUrl :
						'';

					this.getChCredentials(storeUrl, resolve, reject);
				})
					.then((creds) => {
						return this.initSdk(this.config, creds)
							.then(() => this.createBillingAddressComponent(billingAddress))
							.then((billingAddressComponent) => {
								if (billingAddressComponent) {
									formConfig.billingAddress = billingAddressComponent;
								}
							});
					})
					.catch((error) => {
						console.warn('[CH-Adapter] SDK init or billingAddress component failed, continuing without address component.', error);
					});
			}

			initPromise
				.then(() => {
					return window.fiserv.components.paymentFields(formConfig);
				})
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
                throw new Error('Fiserv SDK not loaded or window.fiserv.components.paypal not available');
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
                throw new Error('Fiserv SDK not loaded or window.fiserv.components.paze not available');
            }
            
            // Paze component initialization with displayName and optional cspNonce
            const pazeOptions = {
                displayName: options.displayName || 'My Site'
            };
            
            // Add cspNonce if provided (for security/CSP compliance)
            if (options.cspNonce) {
                pazeOptions.cspNonce = options.cspNonce;
            }
            
            console.log('[CH-Adapter] Loading Paze component with options:', pazeOptions);
            this.pazeComponent = await window.fiserv.components.paze(pazeOptions);
            return this.pazeComponent;
        },

		submitCardForm: function (
			storeUrl,
			runSuccessCb, 
			runErrorCb,
			creds
		) {
			if (typeof(this.sdcv2Form) !== "undefined") {
				if (typeof(creds) === "undefined") {
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
				}
				else {
					let submitConfig = this.buildFormSubmitPayload(creds);
					this.sdcv2Form.submit(submitConfig)
						.then((next) => { 
							let sessionId = creds[this.sessionIdKey];
							runSuccessCb(sessionId); 
						})
						.catch((data) => { console.log(data); runErrorCb(); });
				}
			};
		},

		submitAchForm: function (
			storeUrl,
			runSuccessCb,
			runErrorCb,
			creds
		) {
			this.submitCardForm(storeUrl, runSuccessCb, runErrorCb, creds);
		},

		getAchLegalText: async function () {
			if (this.sdcv2Form === undefined || typeof this.sdcv2Form.getAchLegalText !== 'function') {
				return '';
			}

			return await this.sdcv2Form.getAchLegalText();
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
				"data" : { ...(this.config[this.formConfigKey] || {}) }, 
				"hooks" : {
					"onFormValid" : () => {
						if (typeof this.iframeValidCallback === 'function') {
							this.iframeValidCallback(true);
						}
					},
					"onFormNoLongerValid" : () => {
						if (typeof this.iframeValidCallback === 'function') {
							this.iframeValidCallback(false);
						}
					},
					"onCardBrandChange" : (data) => {
						if (typeof this.cardBrandChangeCallback === 'function') {
							this.cardBrandChangeCallback(data);
						}
					},
					"onFieldValidityChange" : (data) => {
						if (typeof this.fieldValidityHandler === 'function') {
							this.fieldValidityHandler(data);
						}
					},
					"onFocus" : (data) => {
						if (typeof this.fieldFocusHandler === 'function') {
							this.fieldFocusHandler(data);
						}
					},
					"onLostFocus" : (data) => {
						if (typeof this.fieldFocusHandler === 'function') {
							this.fieldFocusHandler(data);
						}
					},
					"onFieldValueChange" : (data) => {
						if (typeof this.fieldValueChangeHandler === 'function') {
							this.fieldValueChangeHandler(data);
						}
					}
				} 

			}; 
			formConfig["data"]["environment"] =  this.config[this.environmentKey];
			
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
