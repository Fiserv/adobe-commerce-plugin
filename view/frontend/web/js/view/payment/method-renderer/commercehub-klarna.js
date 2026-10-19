/* eslint-disable max-lines-per-function, max-params -- AMD uses one factory and one argument per dependency. */
/* eslint-disable compat/compat -- Klarna requires Web Components and Promises; Opera Mini is unsupported. */
/* eslint-disable object-shorthand -- Preserve Magento's classic function wrappers for _super methods. */
define([
	'jquery',
	'Magento_Payment/js/view/payment/cc-form',
	'Magento_Ui/js/model/messageList',
	'Magento_Checkout/js/model/full-screen-loader',
	'Fiserv_Payments/js/action/create-commercehub-enriched-session',
	'Magento_Checkout/js/model/quote',
	'Magento_Checkout/js/model/payment/additional-validators',
	'Magento_Checkout/js/model/shipping-service',
	'require',
	'ko',
	'mage/translate',
], (
	$, Component, globalMessageList, fullScreenLoader, chSession, quote,
	additionalValidators, shippingService, localRequire, ko, $t,
) => Component.extend({
	defaults: {
		template: 'Fiserv_Payments/payment/commercehub/klarna-form',
		code: 'fiserv_klarna',
		additionalData: {},
		paymentPayload: { sessionId: undefined, type: 'klarna' },
		initializedAmount: 0,
		klarnaComponent: undefined,
		klarnaInitialized: false,
	},

	initialize: function () {
		this._super();
		// Keep observables and mutable lifecycle state private to this renderer instance.
		this.isPlaceOrderActionAllowed = ko.observable(false);
		this.additionalData = {};
		this.paymentPayload = { sessionId: undefined, type: 'klarna' };
		this.generation = 0;
		this.subscriptions = [];
		this.listeners = [];
		this.observeBillingAddress();
		this.observeTotals();
		this.watchPaymentMethods();

		return this;
	},

	initializeKlarna: function () {
		// afterRender can run on the radio before its sibling container exists.
		this.scheduleLoad(0);
	},

	observeBillingAddress: function () {
		this.subscriptions.push(quote.billingAddress.subscribe(() => {
			this.isPlaceOrderActionAllowed(Boolean(quote.billingAddress()) && !this.submitting);
		}));
		this.isPlaceOrderActionAllowed(Boolean(quote.billingAddress()));
	},

	observeTotals: function () {
		// Also observes address and shipping changes, including changes during session creation.
		this.quoteState = ko.pureComputed(() => this.getQuoteState());
		this.subscriptions.push(this.quoteState.subscribe(() => {
			this.invalidateSession();
			this.scheduleLoad(300);
		}));
	},

	getAddressState: function (address) {
		if (!address) {
			return [];
		}

		return [
			address.customerAddressId,
			address.firstname,
			address.lastname,
			address.company,
			address.street,
			address.city,
			address.region,
			address.regionId,
			address.regionCode,
			address.region_id,
			address.postcode,
			address.countryId,
			address.country_id,
			address.telephone,
			address.vatId,
			address.customAttributes,
		];
	},

	getQuoteState: function () {
		const totals = quote.totals() || {};
		const method = quote.shippingMethod();

		return JSON.stringify([
			quote.getQuoteId(),
			Number(totals.grand_total),
			this.getCurrency(),
			totals.base_grand_total,
			totals.discount_amount,
			totals.shipping_amount,
			totals.tax_amount,
			totals.items,
			this.getAddressState(quote.billingAddress()),
			this.getAddressState(quote.shippingAddress()),
			method && [method.carrier_code, method.method_code, method.amount],
			shippingService.isLoading(),
		]);
	},

	getCurrency: function () {
		return quote.totals()?.quote_currency_code ||
			globalThis.checkoutConfig?.quoteData?.quote_currency_code ||
			globalThis.checkoutConfig?.totalsData?.quote_currency_code || '';
	},

	canLoad: function () {
		const total = Number(quote.totals()?.grand_total);

		return !this.disposed && !this.submitting && this.getCode() === this.isChecked() &&
			Boolean(quote.billingAddress()) && !shippingService.isLoading() &&
			this.getCurrency() === 'USD' && Number.isFinite(total) && total > 0;
	},

	watchPaymentMethods: function () {
		this.subscriptions.push(quote.paymentMethod.subscribe(() => {
			this.invalidateSession();
			this.scheduleLoad(0);
		}));
		// Delegation supports Magento re-rendering the method list and explicit retry selection.
		this.methodClick = () => this.scheduleLoad(0);
		$(document).on('click.fiservKlarna', '[name="payment[method]"]', this.methodClick);
	},

	scheduleLoad: function (delay) {
		clearTimeout(this.reloadTimer);
		if (this.canLoad()) {
			this.reloadTimer = setTimeout(() => this.loadKlarnaForm(), delay);
		}
	},

	bindContainer: function () {
		const container = document.querySelector('#klarna-button-container');

		if (container && container !== this.container) {
			if (this.container && this.containerDispose) {
				ko.utils.domNodeDisposal.removeDisposeCallback(this.container, this.containerDispose);
			}
			this.container = container;
			this.containerDispose = () => {
				clearTimeout(this.reloadTimer);
				this.invalidateSession();
				this.container = undefined;
			};
			ko.utils.domNodeDisposal.addDisposeCallback(container, this.containerDispose);
		}

		return container;
	},

	loadKlarnaForm: async function () {
		if (!this.canLoad() || this.loading || this.klarnaComponent) {
			return;
		}
		const container = this.bindContainer();

		if (!container) {
			return;
		}
		this.invalidateSession();
		const { generation } = this;
		const snapshot = this.getQuoteState();
		const config = this.getPaymentConfig();

		this.loading = true;
		this.startLoader();
		container.replaceChildren();
		try {
			this.sdkLoadFailed = true;
			await this.loadKlarnaSdk();
			if (!this.isCurrent(generation, snapshot)) {
				return;
			}
			this.sdkLoadFailed = false;
			const response = await chSession({
				merchantName: config.storeName,
				paymentCode: this.getCode(),
				amount: { currency: 'USD', total: Number(quote.totals().grand_total) },
			});

			if (this.isCurrent(generation, snapshot)) {
				this.initializeSession(response, config, generation, snapshot);
			}
		} catch {
			if (this.isCurrent(generation, snapshot)) {
				this.handleKlarnaError();
			}
		} finally {
			if (generation === this.generation) {
				this.loading = false;
				this.stopLoader();
			}
		}
	},

	loadKlarnaSdk: async function () {
		await new Promise((resolve, reject) => {
			localRequire(['SDCv2Library'], resolve, reject);
		});

		if (!globalThis.customElements) {
			throw new Error('Incompatible SDK.');
		}
		await this.waitForComponents();
	},

	initializeSession: function (response, config, generation, snapshot) {
		// Never use SDK event detail as credentials.
		const session = response?.ch_credentials;

		if (typeof session?.sessionId !== 'string' || !session.sessionId.trim() ||
			typeof session.sdkData !== 'string' || !session.sdkData.trim()) {
			throw new Error('Missing payment session or SDK token.');
		}
		this.paymentPayload.sessionId = session.sessionId;
		this.sessionSnapshot = snapshot;
		this.initializedAmount = Number(quote.totals().grand_total);
		this.expiresAt = Date.now() + (10 * 60 * 1000);
		this.mountKlarnaComponent(config, session, generation, snapshot);
	},

	waitForComponents: function () {
		return new Promise((resolve, reject) => {
			const timeout = setTimeout(() => reject(new Error('SDK components unavailable.')), 15_000);

			Promise.all([
				globalThis.customElements.whenDefined('fiserv-context'),
				globalThis.customElements.whenDefined('fiserv-klarna-button'),
			]).then(() => {
				clearTimeout(timeout);
				resolve();
			}, () => {
				clearTimeout(timeout);
				reject(new Error('SDK components unavailable.'));
			});
		});
	},

	isCurrent: function (generation, snapshot) {
		return generation === this.generation && this.canLoad() &&
			snapshot === this.getQuoteState() && Boolean(this.container?.isConnected);
	},

	listen: function (element, type, callback, capture) {
		element.addEventListener(type, callback, Boolean(capture));
		this.listeners.push(() => element.removeEventListener(type, callback, Boolean(capture)));
	},

	mountKlarnaComponent: function (config, session, generation, snapshot) {
		const context = document.createElement('fiserv-context');
		const button = document.createElement('fiserv-klarna-button');
		const current = () => this.isCurrent(generation, snapshot);
		const ready = () => {
			if (current() && this.contextReady && this.buttonReady) {
				clearTimeout(this.readyTimer);
				this.klarnaInitialized = true;
			}
		};

		context.setAttribute('sdk-data', session.sdkData);
		button.setAttribute('theme', config.theme ?? 'default');
		button.setAttribute('shape', config.shape ?? 'default');
		button.setAttribute('logo-alignment', config.logoAlignment ?? 'default');

		// All six SDK listeners are registered while both elements are still detached.
		this.listen(context, 'fiserv-context-init-ready', () => {
			if (current()) {
				this.contextReady = true;
				ready();
			}
		});
		this.listen(context, 'fiserv-context-init-error', () => {
			if (current()) {
				this.handleKlarnaError();
			}
		});
		this.listen(button, 'fiserv-klarna-ready', () => {
			if (current()) {
				this.buttonReady = true;
				ready();
			}
		});
		this.listen(button, 'fiserv-klarna-error', () => {
			if (current()) {
				this.handleKlarnaError();
			}
		});
		this.listen(button, 'fiserv-klarna-success', () => {
			if (current()) {
				this.onKlarnaSuccess();
			}
		});
		this.listen(button, 'fiserv-klarna-cancel', () => {
			if (current()) {
				this.invalidateSession();
				this.showRetry();
			}
		});
		this.contextElement = context;
		this.klarnaComponent = button;
		this.attachContainerClickGuards();
		this.readyTimer = setTimeout(() => {
			if (current() && !this.klarnaInitialized) {
				this.handleKlarnaError();
			}
		}, 30_000);
		this.expiryTimer = setTimeout(() => {
			if (current()) {
				this.invalidateSession();
				this.showRetry();
			}
		}, 10 * 60 * 1000);
		context.append(button);
		this.container.replaceChildren(context);
	},

	attachContainerClickGuards: function () {
		// Do not blanket-preventDefault: the native SDK button must receive valid clicks.
		this.listen(this.contextElement, 'click', (event) => {
			if (this.walletOpen || !this.klarnaInitialized || !this.validate() ||
				!this.isPlaceOrderActionAllowed() || !additionalValidators.validate()) {
				event.preventDefault();
				event.stopImmediatePropagation();

				return;
			}
			this.walletOpen = true;
		}, true);
		// Stop legacy template container click bindings from cancelling the SDK's default action.
		this.listen(this.contextElement, 'click', (event) => event.stopPropagation());
	},

	validate: function () {
		// No card-field validation for this native wallet; Magento's additional validators
		// handle agreements and checkout/billing validation in the inherited placeOrder flow.
		return this.canLoad() && Boolean(this.paymentPayload.sessionId) &&
			this.sessionSnapshot === this.getQuoteState() && Date.now() < this.expiresAt;
	},

	onKlarnaSuccess: function () {
		if (!this.klarnaInitialized || !this.validate() || this.approvalHandled) {
			return;
		}
		this.approvalHandled = true;
		this.walletOpen = false;
		this.additionalData = {
			payment_source: 'klarna',
			payment_session: this.paymentPayload.sessionId,
		};
		// Success has NO payload. Submit only the original backend-created session reference.
		try {
			if (this.placeOrder('parent') === false) {
				this.invalidateSession();
				this.showRetry();
			}
		} catch {
			this.submitting = false;
			this.handleKlarnaError();
		}
	},

	placeOrder: function (key, event) {
		if (key !== 'parent' || this.submitting || !this.approvalHandled || !this.validate()) {
			return false;
		}

		// Parent performs additionalValidators (including agreements), loader, and redirect.
		// Its request is created synchronously; lock there, after parent validation succeeds.
		return this._super(undefined, event);
	},

	getPlaceOrderDeferredObject: function () {
		this.submitting = true;
		const request = this._super();

		request.fail(() => {
			if (!this.disposed) {
				this.submitting = false;
				this.invalidateSession();
				this.showRetry();
			}
		});

		return request;
	},

	getData: function () {
		return {
			method: this.getCode(),
			additional_data: {
				payment_source: 'klarna',
				payment_session: this.additionalData.payment_session,
			},
		};
	},

	invalidateSession: function () {
		this.generation += 1;
		clearTimeout(this.readyTimer);
		clearTimeout(this.expiryTimer);
		for (const remove of this.listeners.splice(0)) {
			remove();
		}
		this.contextElement?.remove();
		this.contextElement = undefined;
		this.klarnaComponent = undefined;
		this.klarnaInitialized = false;
		this.contextReady = false;
		this.buttonReady = false;
		this.walletOpen = false;
		this.approvalHandled = false;
		this.loading = false;
		this.paymentPayload.sessionId = undefined;
		this.additionalData = {};
		this.sessionSnapshot = undefined;
		this.expiresAt = 0;
		this.initializedAmount = 0;
		this.stopLoader();
		this.isPlaceOrderActionAllowed(Boolean(quote.billingAddress()) && !this.submitting);
	},

	handleKlarnaError: function () {
		this.invalidateSession();
		// Never render or log SDK error details, which can include session credentials.
		this.showError($t('Klarna checkout could not be completed. Please try again.'));
		this.showRetry();
	},

	showRetry: function () {
		if (this.disposed || !this.container?.isConnected || this.getCode() !== this.isChecked()) {
			return;
		}
		const retry = document.createElement('button');

		retry.type = 'button';
		retry.className = 'action secondary';
		retry.textContent = this.sdkLoadFailed ? $t('Reload checkout') : $t('Retry Klarna');
		retry.addEventListener('click', (event) => {
			event.preventDefault();
			event.stopPropagation();
			if (this.sdkLoadFailed) {
				// A failed/partial custom-element registration cannot safely be loaded twice.
				globalThis.location.reload();

				return;
			}
			this.loadKlarnaForm();
		});
		this.container.replaceChildren(retry);
	},

	startLoader: function () {
		if (!this.loaderActive) {
			this.loaderActive = true;
			fullScreenLoader.startLoader();
		}
	},

	stopLoader: function () {
		if (this.loaderActive) {
			this.loaderActive = false;
			fullScreenLoader.stopLoader();
		}
	},

	getPaymentConfig: function () {
		return globalThis.checkoutConfig?.payment?.[this.getCode()] || {};
	},

	showError: function (message) {
		globalMessageList.addErrorMessage({ message });
	},

	getCode: function () {
		return this.code;
	},

	showPrivacyStatement: function () {
		return this.getPaymentConfig().show_privacy_statement;
	},

	isBillingAddressRequired: function () {
		return true;
	},

	isActive: function () {
		const active = this.getCode() === this.isChecked();

		this.active(active);

		return active;
	},

	destroy: function () {
		this.disposed = true;
		clearTimeout(this.reloadTimer);
		this.invalidateSession();
		for (const subscription of this.subscriptions) {
			subscription.dispose();
		}
		this.quoteState.dispose();
		$(document).off('click.fiservKlarna', '[name="payment[method]"]', this.methodClick);
		if (this.container && this.containerDispose) {
			ko.utils.domNodeDisposal.removeDisposeCallback(this.container, this.containerDispose);
		}

		return this._super();
	},
}));
