/* eslint-disable max-params, max-lines-per-function, prefer-arrow-callback, unicorn/prefer-module */
/* eslint-disable unicorn/prefer-global-this */
/* eslint-disable no-invalid-this, object-shorthand, complexity, unicorn/no-null, eqeqeq */
/* eslint-disable unicorn/explicit-length-check, @stylistic/no-mixed-operators */
define([
	'Magento_Payment/js/view/payment/cc-form',
	'Fiserv_Payments/js/ch-adapter',
	'Fiserv_Payments/js/action/create-commercehub-enriched-session',
	'Magento_Checkout/js/model/quote',
	'Magento_Ui/js/model/messageList',
	'Magento_Checkout/js/model/full-screen-loader',
	'ko',
	'mage/translate',
	'domReady!',
], function (
	Component,
	chAdapter,
	commerceHubSession,
	quote,
	globalMessageList,
	fullScreenLoader,
	ko,
	$t,
) {
	'use strict';

	return Component.extend({
		isPlaceOrderActionAllowed: ko.observable(false),

		defaults: {
			template: 'Fiserv_Payments/payment/commercehub/cashapp-form',
			code: 'fiserv_cashapp',
			active: false,
			additionalData: {},
			cashAppHandle: null,
			cashAppCredentials: null,
			initializationPromise: null,
			initializationGeneration: 0,
			pendingInitialization: false,
			initializedAmount: null,
			reinitializeTimer: null,
		},

		initialize: function () {
			this._super();
			this.isPlaceOrderActionAllowed(Boolean(quote.billingAddress()));

			quote.billingAddress.subscribe(function (address) {
				this.isPlaceOrderActionAllowed(Boolean(address));
				if (address && this.isChecked() === this.getCode()) {
					this.initializeCashApp();
				}
			}, this);

			quote.totals.subscribe(function (totals) {
				const amount = totals && Number(totals.grand_total);

				if (
					this.isChecked() === this.getCode() &&
					this.initializedAmount !== null &&
					amount !== this.initializedAmount
				) {
					clearTimeout(this.reinitializeTimer);
					this.reinitializeTimer = setTimeout(function () {
						this.destroyCashApp();
						this.initializeCashApp();
					}.bind(this), 250);
				}
			}, this);

			this.isChecked.subscribe(function (method) {
				if (method === this.getCode()) {
					this.initializeCashApp();
				} else {
					this.destroyCashApp();
				}
			}, this);

			return this;
		},

		afterRender: function () {
			if (this.isChecked() === this.getCode()) {
				this.initializeCashApp();
			}
		},

		initializeCashApp: function () {
			if (this.initializationPromise) {
				this.pendingInitialization = true;

				return this.initializationPromise;
			}

			if (!this.isPlaceOrderActionAllowed() || this.cashAppHandle) {
				return this.initializationPromise || Promise.resolve(this.cashAppHandle);
			}

			this.initializationPromise = this.createCashAppButton();

			return this.initializationPromise;
		},

		createCashAppButton: async function () {
			const config = window.checkoutConfig && window.checkoutConfig.payment &&
				window.checkoutConfig.payment[this.getCode()] || {};
			const generation = this.initializationGeneration;
			let sdkMode;
			let handle;

			fullScreenLoader.startLoader();
			try {
				sdkMode = await this.initializeCashAppSdk(config);
				this.clearContainer();
				handle = sdkMode === 'component' ?
					await window.fiserv.components.cashApp(this.getCashAppOptions(config)) :
					await this.createCashAppElements(config);
				if (generation !== this.initializationGeneration || this.isChecked() !== this.getCode()) {
					if (handle && typeof handle.destroy === 'function') {
						handle.destroy();
					}

					return null;
				}

				this.cashAppHandle = handle;
				this.initializedAmount = this.getGrandTotal();
			} catch (error) {
				this.handleError(error);
			} finally {
				this.initializationPromise = null;
				fullScreenLoader.stopLoader();
				if (this.pendingInitialization && this.isChecked() === this.getCode()) {
					this.pendingInitialization = false;
					setTimeout(this.initializeCashApp.bind(this), 0);
				}
			}

			return this.cashAppHandle;
		},

		initializeCashAppSdk: async function (config) {
			const hasComponentApi = window.fiserv && window.fiserv.components &&
				typeof window.fiserv.components.cashApp === 'function';
			const hasElementApi = window.customElements &&
				window.customElements.get('fiserv-context') &&
				window.customElements.get('fiserv-cashapp-button');

			if (!hasComponentApi && !hasElementApi) {
				throw new Error($t('Fiserv SDK is not loaded. Please check your network and configuration.'));
			}

			chAdapter.initialize(config);
			const credentialsResponse = await commerceHubSession({ paymentCode: this.getCode() });
			const credentials = credentialsResponse && credentialsResponse[chAdapter.credentialsKey];

			if (!credentials) {
				throw new Error($t('No credentials returned for the Cash App session.'));
			}

			this.additionalData.payment_session = credentials[chAdapter.sessionIdKey];
			this.cashAppCredentials = credentials;

			if (hasComponentApi) {
				if (typeof window.fiserv.init !== 'function') {
					throw new TypeError($t('Fiserv SDK initialization is not available.'));
				}

				await chAdapter.initSdk(config, credentials);

				return 'component';
			}

			return 'elements';
		},

		createCashAppElements: async function (config) {
			const container = document.querySelector('#cashapp-button-container');
			const credentials = this.cashAppCredentials || {};
			const buttonConfig = config.buttonConfig || {};
			const contextElement = document.createElement('fiserv-context');
			const buttonElement = document.createElement('fiserv-cashapp-button');
			const readyHandler = () => {
				this.initializedAmount = this.getGrandTotal();
			};
			const approveHandler = (event) => this.handleApprove(event && event.detail);
			const cancelHandler = (event) => this.handleCancel(event && event.detail);
			const errorHandler = (event) => this.handleError(event && event.detail || event);
			const contextErrorHandler = (event) => this.handleError(event && event.detail || event);

			if (!container) {
				throw new Error($t('Cash App button container was not found.'));
			}

			this.configureCashAppContext(contextElement, config, credentials);

			buttonElement.id = 'cashapp-button';
			buttonElement.setAttribute('theme', buttonConfig.theme || 'DARK');
			buttonElement.setAttribute('shape', buttonConfig.shape || 'SEMIROUND');
			buttonElement.setAttribute('size', buttonConfig.size || 'MEDIUM');
			buttonElement.setAttribute('width', buttonConfig.width || 'STATIC');
			buttonElement.addEventListener('fiserv-cashapp-ready', readyHandler);
			buttonElement.addEventListener('fiserv-cashapp-success', approveHandler);
			buttonElement.addEventListener('fiserv-cashapp-cancel', cancelHandler);
			buttonElement.addEventListener('fiserv-cashapp-error', errorHandler);
			contextElement.addEventListener('fiserv-context-init-error', contextErrorHandler);
			contextElement.append(buttonElement);
			container.append(contextElement);

			if (typeof contextElement.waitForContext === 'function') {
				await contextElement.waitForContext();
			}

			return {
				destroy: function () {
					buttonElement.removeEventListener('fiserv-cashapp-ready', readyHandler);
					buttonElement.removeEventListener('fiserv-cashapp-success', approveHandler);
					buttonElement.removeEventListener('fiserv-cashapp-cancel', cancelHandler);
					buttonElement.removeEventListener('fiserv-cashapp-error', errorHandler);
					contextElement.removeEventListener('fiserv-context-init-error', contextErrorHandler);
					contextElement.remove();
				},
			};
		},

		configureCashAppContext: function (contextElement, config, credentials) {
			const sdkData = credentials[chAdapter.sdkDataKey];

			if (sdkData) {
				contextElement.setAttribute('sdk-data', sdkData);

				return;
			}

			const legacyParameters = {
				environment: config.environment,
				'api-key': config.apiKey,
				'access-token': credentials[chAdapter.accessTokenKey],
				'merchant-id': config.merchantId,
			};
			const missingParameters = Object.keys(legacyParameters).filter(function (name) {
				return !legacyParameters[name];
			});

			if (missingParameters.length > 0) {
				throw new Error($t('The Cash App session did not return valid SDK initialization data.'));
			}

			for (const name of Object.keys(legacyParameters)) {
				contextElement.setAttribute(name, legacyParameters[name]);
			}
			this.setCashAppAttribute(contextElement, 'terminal-id', config.terminalId);
		},

		setCashAppAttribute: function (element, name, value) {
			if (value !== undefined && value !== null && value !== '') {
				element.setAttribute(name, value);
			}
		},

		getCashAppOptions: function (config) {
			const buttonConfig = config.buttonConfig || {};

			return {
				data: {
					button: {
						parentElementId: 'cashapp-button-container',
						theme: buttonConfig.theme || 'DARK',
						shape: buttonConfig.shape || 'SEMIROUND',
						size: buttonConfig.size || 'MEDIUM',
						width: buttonConfig.width || 'STATIC',
					},
				},
				hooks: {
					onApprove: this.handleApprove.bind(this),
					onCancel: this.handleCancel.bind(this),
					onError: this.handleError.bind(this),
				},
			};
		},

		handleApprove: function () {
			if (!this.isPlaceOrderActionAllowed()) {
				return;
			}

			this.placeOrder();
		},

		handleCancel: function () {
			globalMessageList.addNoticeMessage({
				message: $t('Cash App checkout was cancelled. Please try again.'),
			});
		},

		handleError: function (error) {
			const message = error && (error.message || error.errorCode) || $t('Unable to initialize Cash App Pay.');

			console.error('[Cash App]', error);
			globalMessageList.addErrorMessage({ message: message });
		},

		destroyCashApp: function () {
			clearTimeout(this.reinitializeTimer);
			this.initializationGeneration += 1;
			if (this.cashAppHandle && typeof this.cashAppHandle.destroy === 'function') {
				this.cashAppHandle.destroy();
			}

			this.cashAppHandle = null;
			this.initializedAmount = null;
			this.clearContainer();
		},

		clearContainer: function () {
			const container = document.querySelector('#cashapp-button-container');

			if (container) {
				container.textContent = '';
			}
		},

		getGrandTotal: function () {
			const totals = quote.totals();

			return totals ? Number(totals.grand_total) : null;
		},

		getData: function () {
			return {
				method: this.getCode(),
				additional_data: this.additionalData,
			};
		},

		getCode: function () {
			return this.code;
		},
		isActive: function () {
			const active = this.getCode() === this.isChecked();

			this.active(active);

			return active;
		},

		initObservable: function () {
			this._super().observe(['active']);

			return this;
		},
	});
});
