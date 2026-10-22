/* eslint-disable max-lines-per-function, max-params -- AMD uses one factory and one argument per dependency. */
/* eslint-disable compat/compat -- Afterpay requires Web Components and Promises; Opera Mini is unsupported. */
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
	'Magento_Customer/js/model/customer',
	'require',
	'ko',
	'mage/translate',
], (
	$, Component, globalMessageList, fullScreenLoader, chSession, quote,
	additionalValidators, shippingService, customer, localRequire, ko, $t,
) => Component.extend({
	defaults: {
		template: 'Fiserv_Payments/payment/commercehub/afterpay-form',
		code: 'fiserv_afterpay',
		additionalData: {},
		paymentPayload: { sessionId: undefined, type: 'afterpay' },
		initializedAmount: 0,
		afterpayComponent: undefined,
		afterpayInitialized: false,
	},

	initialize: function () {
		this._super();
		// Observables, credentials, and mutable lifecycle state belong to this instance.
		this.isPlaceOrderActionAllowed = ko.observable(false);
		this.additionalData = {};
		this.paymentPayload = { sessionId: undefined, type: 'afterpay' };
		this.generation = 0;
		this.subscriptions = [];
		this.listeners = [];
		this.pendingWaits = new Set();
		this.disposed = false;
		this.submitting = false;
		this.loading = false;
		this.shopperEmail = ko.observable(this.readShopperEmail());
		this.observeShopperEmail();
		this.observeBillingAddress();
		this.observeTotals();
		this.watchPaymentMethods();

		return this;
	},

	initializeAfterpay: function () {
		// afterRender can run on the radio before its sibling container exists.
		this.scheduleLoad(0);
	},

	readShopperEmail: function () {
		const email = customer.isLoggedIn() ? customer.customerData?.email : (quote.guestEmail || $('#customer-email').val());
		const value = typeof email === 'string' ? email.trim() : '';

		// Mirrors the SDK's validation so a half-typed guest email never reaches customer-email.
		return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value) ? value : '';
	},

	observeShopperEmail: function () {
		// quote.guestEmail is a plain property, so re-read it when the checkout email field settles.
		this.emailHandler = () => this.shopperEmail(this.readShopperEmail());
		$(document).on('change.fiservAfterpay blur.fiservAfterpay', '#customer-email', this.emailHandler);
		// Shipping-step completion also stores the email; re-sync when it is saved.
		this.subscriptions.push(shippingService.isLoading.subscribe(this.emailHandler));
	},

	observeBillingAddress: function () {
		this.subscriptions.push(quote.billingAddress.subscribe(() => {
			this.isPlaceOrderActionAllowed(Boolean(quote.billingAddress()) && !this.submitting);
		}));
		this.isPlaceOrderActionAllowed(Boolean(quote.billingAddress()));
	},

	observeTotals: function () {
		// Address/shipping changes also invalidate in-flight session responses.
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
			this.getBaseCurrency(),
			totals.base_grand_total,
			totals.discount_amount,
			totals.shipping_amount,
			totals.tax_amount,
			totals.items,
			this.getAddressState(quote.billingAddress()),
			this.getAddressState(quote.shippingAddress()),
			method && [method.carrier_code, method.method_code, method.amount],
			shippingService.isLoading(),
			// An email change after mount must remount with a fresh session (invalidateAfterpay equivalent).
			this.shopperEmail(),
		]);
	},

	getCurrency: function () {
		return quote.totals()?.quote_currency_code ||
            globalThis.checkoutConfig?.quoteData?.quote_currency_code ||
            globalThis.checkoutConfig?.totalsData?.quote_currency_code || '';
	},

	getBaseCurrency: function () {
		return quote.totals()?.base_currency_code ||
            globalThis.checkoutConfig?.quoteData?.base_currency_code ||
            globalThis.checkoutConfig?.totalsData?.base_currency_code || '';
	},

	isCurrencySupported: function () {
		const supported = this.getPaymentConfig().supportedCurrencies || [];

		return supported.includes(this.getCurrency());
	},

	hasValidAmount: function () {
		const total = Number(quote.totals()?.grand_total);

		return Number.isFinite(total) && total > 0;
	},

	canLoad: function () {
		return !this.disposed && !this.submitting && this.getCode() === this.isChecked() &&
			Boolean(this.getPaymentConfig().isActive) && Boolean(quote.billingAddress()) &&
			!shippingService.isLoading() && this.isCurrencySupported() && this.hasValidAmount();
	},

	watchPaymentMethods: function () {
		this.subscriptions.push(quote.paymentMethod.subscribe(() => {
			this.invalidateSession();
			this.scheduleLoad(0);
		}));
		// Delegation supports re-rendering the method list.
		this.methodClick = (event) => {
			if (event.currentTarget.id !== this.getCode()) {
				this.invalidateSession();
			}
			this.scheduleLoad(0);
		};
		$(document).on('click.fiservAfterpay', '[name="payment[method]"]', this.methodClick);
	},

	scheduleLoad: function (delay) {
		clearTimeout(this.reloadTimer);
		if (this.canLoad()) {
			this.reloadTimer = setTimeout(() => this.loadAfterpayForm(), delay);
		}
	},

	bindContainer: function () {
		const container = document.querySelector('#afterpay-button-container');

		if (container && container !== this.container) {
			if (this.container && this.containerDispose) {
				ko.utils.domNodeDisposal.removeDisposeCallback(this.container, this.containerDispose);
			}
			this.container = container;
			this.containerDispose = () => {
				this.invalidateSession();
				this.container = undefined;
			};
			ko.utils.domNodeDisposal.addDisposeCallback(container, this.containerDispose);
		}

		return container;
	},

	loadAfterpayForm: async function () {
		if (!this.canLoad() || this.loading || this.afterpayComponent) {
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
			await this.loadAfterpaySdk();
			if (!this.isCurrent(generation, snapshot)) {
				return;
			}
			const response = await this.waitFor(chSession({
				merchantName: config.storeName,
				paymentCode: this.getCode(),
				amount: { currency: this.getCurrency(), total: Number(quote.totals().grand_total) },
			}), 30_000);

			if (this.isCurrent(generation, snapshot)) {
				this.initializeSession(response, config, generation, snapshot);
			}
		} catch {
			if (this.isCurrent(generation, snapshot)) {
				this.handleAfterpayError();
			}
		} finally {
			if (generation === this.generation) {
				this.loading = false;
				this.stopLoader();
			}
		}
	},

	loadAfterpaySdk: async function () {
		await this.waitFor(new Promise((resolve, reject) => {
			localRequire(['SDCv2Library'], resolve, reject);
		}), 15_000);

		if (!globalThis.customElements) {
			throw new Error('Incompatible SDK.');
		}
		await this.waitForComponents();
	},

	waitForComponents: function () {
		return this.waitFor(Promise.all([
			globalThis.customElements.whenDefined('fiserv-context'),
			globalThis.customElements.whenDefined('fiserv-afterpay-button'),
		]), 15_000);
	},

	waitFor: function (promise, duration) {
		// Cancelling invalidated work clears its timeout and rejects only into the stale guard.
		return new Promise((resolve, reject) => {
			const timer = {};
			const cancel = () => {
				clearTimeout(timer.id);
				this.pendingWaits.delete(cancel);
				reject(new Error('Payment initialization unavailable.'));
			};

			timer.id = setTimeout(cancel, duration);
			this.pendingWaits.add(cancel);
			Promise.resolve(promise).then((value) => {
				clearTimeout(timer.id);
				this.pendingWaits.delete(cancel);
				resolve(value);
			}, () => cancel());
		});
	},

	initializeSession: function (response, config, generation, snapshot) {
		// SDK event detail is never a source of credentials.
		const session = response?.ch_credentials;

		if (typeof session?.sessionId !== 'string' || !session.sessionId.trim() ||
            typeof session.sdkData !== 'string' || !session.sdkData.trim()) {
			throw new Error('Missing payment session or SDK token.');
		}
		this.paymentPayload.sessionId = session.sessionId;
		this.sessionSnapshot = snapshot;
		this.initializedAmount = Number(quote.totals().grand_total);
		this.expiresAt = Date.now() + (10 * 60 * 1000);
		this.mountAfterpayComponent(config, session, generation, snapshot);
	},

	isCurrent: function (generation, snapshot) {
		return generation === this.generation && this.canLoad() &&
            snapshot === this.getQuoteState() && Boolean(this.container?.isConnected);
	},

	listen: function (element, type, callback, capture) {
		element.addEventListener(type, callback, Boolean(capture));
		this.listeners.push(() => element.removeEventListener(type, callback, Boolean(capture)));
	},

	mountAfterpayComponent: function (config, session, generation, snapshot) {
		const context = document.createElement('fiserv-context');
		const button = document.createElement('fiserv-afterpay-button');
		const current = () => this.isCurrent(generation, snapshot);
		const ready = () => {
			if (current() && this.contextReady && this.buttonReady) {
				clearTimeout(this.readyTimer);
				this.afterpayInitialized = true;
			}
		};

		context.setAttribute('sdk-data', session.sdkData);
		button.setAttribute('theme', ['mint', 'black', 'white'].includes(config.theme) ? config.theme : 'mint');
		button.setAttribute('shape', ['round', 'semiround'].includes(config.shape) ? config.shape : 'round');
		button.setAttribute('size', ['small', 'medium', 'large'].includes(config.size) ? config.size : 'medium');
		if (this.shopperEmail()) {
			// Without it Afterpay shows its own email prompt after the click.
			button.setAttribute('customer-email', this.shopperEmail());
		}
		button.setAttribute('width', ['static', 'full'].includes(config.width) ? config.width : 'static');

		// Register every SDK listener before either element is connected.
		this.listen(context, 'fiserv-context-init-ready', () => {
			if (current()) {
				this.contextReady = true;
				ready();
			}
		});
		this.listen(context, 'fiserv-context-init-error', () => {
			if (current()) {
				this.handleAfterpayError();
			}
		});
		this.listen(button, 'fiserv-afterpay-ready', () => {
			if (current()) {
				this.buttonReady = true;
				ready();
			}
		});
		this.listen(button, 'fiserv-afterpay-error', () => {
			if (current()) {
				this.handleAfterpayError();
			}
		});
		this.listen(button, 'fiserv-afterpay-success', () => {
			if (current()) {
				this.onAfterpaySuccess();
			}
		});
		this.listen(button, 'fiserv-afterpay-cancel', () => {
			if (current()) {
				this.invalidateSession();
			}
		});
		this.contextElement = context;
		this.afterpayComponent = button;
		this.attachContainerClickGuards();
		this.readyTimer = setTimeout(() => {
			if (current() && !this.afterpayInitialized) {
				this.handleAfterpayError();
			}
		}, 30_000);
		this.expiryTimer = setTimeout(() => {
			if (current()) {
				this.invalidateSession();
			}
		}, 10 * 60 * 1000);
		context.append(button);
		this.container.replaceChildren(context);
	},

	attachContainerClickGuards: function () {
		// Valid clicks must reach the native SDK button; do not blanket-preventDefault.
		this.listen(this.contextElement, 'click', (event) => {
			if (this.walletOpen || !this.afterpayInitialized || !this.validate() ||
                !this.isPlaceOrderActionAllowed() || !additionalValidators.validate()) {
				event.preventDefault();
				event.stopImmediatePropagation();

				return;
			}
			this.walletOpen = true;
		}, true);
		// Prevent legacy template bindings from cancelling the native button action.
		this.listen(this.contextElement, 'click', (event) => event.stopPropagation());
	},

	validate: function () {
		// Skip card-field validation; parent checkout still runs additional validators.
		return this.canLoad() && Boolean(this.paymentPayload.sessionId) &&
            this.sessionSnapshot === this.getQuoteState() && Date.now() < this.expiresAt;
	},

	onAfterpaySuccess: function () {
		if (!this.afterpayInitialized || !this.validate() || this.approvalHandled) {
			return;
		}
		this.approvalHandled = true;
		this.approvalPending = true;
		this.walletOpen = false;
		this.additionalData = {
			payment_type: 'afterpay',
			payment_session: this.paymentPayload.sessionId,
		};
		// Submit only the original backend session reference, never SDK event data.
		try {
			if (this.placeOrder('parent') === false) {
				this.invalidateSession();
			}
		} catch {
			this.submitting = false;
			this.handleAfterpayError();
		}
	},

	placeOrder: function (key, event) {
		if (key !== 'parent' || this.submitting || !this.approvalHandled ||
            !this.approvalPending || !this.validate()) {
			return false;
		}
		this.approvalPending = false;

		// Parent runs additionalValidators, including checkout agreements, and redirects.
		return this._super(undefined, event);
	},

	getPlaceOrderDeferredObject: function () {
		this.submitting = true;
		const request = this._super();

		request.fail(() => {
			if (!this.disposed) {
				this.submitting = false;
				this.invalidateSession();
			}
		});

		return request;
	},

	getData: function () {
		return {
			method: this.getCode(),
			additional_data: {
				payment_type: 'afterpay',
				payment_session: this.additionalData.payment_session,
			},
		};
	},

	invalidateSession: function () {
		this.generation += 1;
		clearTimeout(this.reloadTimer);
		clearTimeout(this.readyTimer);
		clearTimeout(this.expiryTimer);
		for (const cancel of this.pendingWaits) {
			cancel();
		}
		for (const remove of this.listeners.splice(0)) {
			remove();
		}
		this.contextElement?.remove();
		this.contextElement = undefined;
		this.afterpayComponent = undefined;
		this.afterpayInitialized = false;
		this.contextReady = false;
		this.buttonReady = false;
		this.walletOpen = false;
		this.approvalHandled = false;
		this.approvalPending = false;
		this.loading = false;
		this.paymentPayload.sessionId = undefined;
		this.additionalData = {};
		this.sessionSnapshot = undefined;
		this.expiresAt = 0;
		this.initializedAmount = 0;
		this.stopLoader();
		this.isPlaceOrderActionAllowed(!this.disposed && Boolean(quote.billingAddress()) && !this.submitting);
	},

	handleAfterpayError: function () {
		this.invalidateSession();
		// Never render or log SDK errors: they can contain session credentials.
		this.showError($t('Afterpay checkout could not be completed. Please try again.'));
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
		this.invalidateSession();
		for (const subscription of this.subscriptions) {
			subscription.dispose();
		}
		this.quoteState.dispose();
		$(document).off('click.fiservAfterpay', '[name="payment[method]"]', this.methodClick);
		$(document).off('change.fiservAfterpay blur.fiservAfterpay', '#customer-email', this.emailHandler);
		if (this.container && this.containerDispose) {
			ko.utils.domNodeDisposal.removeDisposeCallback(this.container, this.containerDispose);
		}

		return this._super();
	},
}));
