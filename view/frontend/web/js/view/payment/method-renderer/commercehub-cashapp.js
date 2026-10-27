define([
    'jquery',
    'Magento_Payment/js/view/payment/cc-form',
    'Magento_Ui/js/model/messageList',
    'Magento_Checkout/js/model/full-screen-loader',
    'Fiserv_Payments/js/ch-adapter',
    'Fiserv_Payments/js/action/create-commercehub-enriched-session',
    'Magento_Checkout/js/model/quote',
    'ko',
    'mage/translate',
    'mage/cookies'
], function (
    $,
    Component,
    globalMessageList,
    fullScreenLoader,
    chAdapter,
    chSession,
    quote,
    ko,
    $t
) {
    'use strict';

    const CONTAINER_ID = 'cashapp-button-container';

    return Component.extend({
        defaults: {
            template: 'Fiserv_Payments/payment/commercehub/cashapp-form',
            code: 'fiserv_cashapp',
            additionalData: {},
            paymentPayload: {
                sessionId: null,
                orderId: null,
                type: 'cashapp',
                publicKeyHash: null
            },
            isPlaceOrderActionAllowed: ko.observable(false),
            initializedAmount: 0.00,
            cashAppComponent: null,
            cashAppInitialized: false,
            loadGeneration: 0,
            loading: false,
            reloadPending: false,
            totalsSubscription: null,
            savedTokens: [],
            savedTokenNotice: '',
            canSave: false
        },

        initObservable: function () {
            this._super().observe([
                'active',
                'selectedToken',
                'savePaymentMethod'
            ]);

            return this;
        },

        initialize: function () {
            this._super();

            const config = this.getPaymentConfig();

            this.savedTokens = Array.isArray(config.savedTokens) ? config.savedTokens : [];
            this.savedTokenNotice = config.savedTokenNotice || '';
            this.canSave = Boolean(config.canSave) && this.savedTokens.length === 0;
            this.selectedToken(this.savedTokens.length ? this.savedTokens[0].publicHash : '');

            this.selectedToken.subscribe(() => this.restartCashApp());

            this.observeBillingAddress();

            this.isChecked.subscribe((method) => {
                if (method === this.getCode()) {
                    this.loadCashAppForm();
                } else {
                    this.destroyCashApp();
                }
            });

            return this;
        },

        /**
         * afterRender target of the template.
         */
        initializeCashApp: async function () {
            if (this.getCode() === this.isChecked()) {
                await this.loadCashAppForm();
            }
        },

        observeBillingAddress: function () {
            this.isPlaceOrderActionAllowed(Boolean(quote.billingAddress()));

            quote.billingAddress.subscribe((address) => {
                this.isPlaceOrderActionAllowed(address !== null);
                if (address && this.getCode() === this.isChecked()) {
                    this.loadCashAppForm();
                }
            });
        },

        observeTotals: function () {
            if (this.totalsSubscription) {
                return;
            }

            this.totalsSubscription = quote.totals.subscribe((totals) => {
                if (!totals || typeof totals.grand_total === 'undefined') {
                    return;
                }

                // A Cash App session is bound to the amount it was created for.
                if (
                    this.getCode() === this.isChecked() &&
                    !this.selectedToken() &&
                    Number(this.initializedAmount) > 0 &&
                    totals.grand_total != this.initializedAmount
                ) {
                    this.loadCashAppForm();
                }
            });
        },

        loadCashAppForm: async function () {
            if (this.selectedToken() || !this.isPlaceOrderActionAllowed()) {
                return;
            }

            if (this.loading) {
                this.reloadPending = true;
                return;
            }

            const config = this.getPaymentConfig();
            const generation = ++this.loadGeneration;
            const isStale = () => generation !== this.loadGeneration ||
                this.selectedToken() ||
                this.getCode() !== this.isChecked();

            this.loading = true;
            try {
                fullScreenLoader.startLoader();
                this.destroyCashApp(false);
                $('#' + CONTAINER_ID).empty();

                chAdapter.initialize(config);
                const creds = await chSession({
                    paymentCode: this.getCode(),
                    form_key: $.mage.cookies.get('form_key')
                });
                const credentials = creds && creds[chAdapter.credentialsKey];

                if (isStale()) {
                    return;
                }

                if (!credentials) {
                    throw new Error($t('No credentials returned for the Cash App session.'));
                }

                this.paymentPayload.sessionId = credentials[chAdapter.sessionIdKey];
                this.additionalData.payment_session = this.paymentPayload.sessionId;

                await chAdapter.initSdk(config, credentials);

                if (isStale()) {
                    return;
                }

                const component = await this.mountCashAppComponent(config, credentials, generation);

                if (isStale()) {
                    this.destroyHandle(component);
                    return;
                }

                this.cashAppComponent = component;
                this.initializedAmount = quote.totals()['grand_total'];
                this.observeTotals();
            } catch (error) {
                if (generation === this.loadGeneration && !this.selectedToken()) {
                    this.showError(error);
                }
            } finally {
                this.loading = false;
                fullScreenLoader.stopLoader();
                if (this.reloadPending) {
                    this.reloadPending = false;
                    setTimeout(() => this.loadCashAppForm(), 0);
                }
            }
        },

        mountCashAppComponent: async function (config, credentials, generation) {
            const buttonConfig = config.buttonConfig || {};
            const hasComponent = window.fiserv && window.fiserv.components &&
                typeof window.fiserv.components.cashApp === 'function';

            if (hasComponent) {
                const component = await chAdapter.loadCashAppComponent({
                    data: {
                        button: {
                            parentElementId: CONTAINER_ID,
                            theme: buttonConfig.theme || 'DARK',
                            shape: buttonConfig.shape || 'SEMIROUND',
                            size: buttonConfig.size || 'MEDIUM',
                            width: buttonConfig.width || 'STATIC'
                        }
                    },
                    hooks: {
                        onApprove: () => this.onCashAppApprove(generation),
                        onCancel: () => this.onCashAppCancel(),
                        onError: (error) => this.showError(error)
                    }
                });

                this.cashAppInitialized = Boolean(component);

                return component;
            }

            if (window.customElements && window.customElements.get('fiserv-context') &&
                window.customElements.get('fiserv-cashapp-button')) {
                const handle = await this.mountCashAppElements(buttonConfig, credentials, generation);

                this.cashAppInitialized = Boolean(handle);

                return handle;
            }

            throw new Error($t('Fiserv SDK is not loaded. Please check your network and configuration.'));
        },

        /**
         * Custom-element fallback when the SDK does not expose components.cashApp.
         */
        mountCashAppElements: async function (buttonConfig, credentials, generation) {
            const container = document.getElementById(CONTAINER_ID);

            if (!container) {
                throw new Error($t('Cash App button container was not found.'));
            }

            const sdkData = credentials[chAdapter.sdkDataKey];

            if (!sdkData) {
                throw new Error($t('The Cash App session did not return valid SDK initialization data.'));
            }

            const contextElement = document.createElement('fiserv-context');
            const buttonElement = document.createElement('fiserv-cashapp-button');
            const onSuccess = () => this.onCashAppApprove(generation);
            const onCancel = () => this.onCashAppCancel();
            const onError = (event) => this.showError(event && event.detail || event);

            contextElement.setAttribute('sdk-data', sdkData);
            buttonElement.id = 'cashapp-button';
            buttonElement.setAttribute('theme', buttonConfig.theme || 'DARK');
            buttonElement.setAttribute('shape', buttonConfig.shape || 'SEMIROUND');
            buttonElement.setAttribute('size', buttonConfig.size || 'MEDIUM');
            buttonElement.setAttribute('width', buttonConfig.width || 'STATIC');
            buttonElement.addEventListener('fiserv-cashapp-success', onSuccess);
            buttonElement.addEventListener('fiserv-cashapp-cancel', onCancel);
            buttonElement.addEventListener('fiserv-cashapp-error', onError);
            contextElement.addEventListener('fiserv-context-init-error', onError);
            contextElement.append(buttonElement);
            container.append(contextElement);

            if (typeof contextElement.waitForContext === 'function') {
                await contextElement.waitForContext();
            }

            return {
                destroy: () => {
                    buttonElement.removeEventListener('fiserv-cashapp-success', onSuccess);
                    buttonElement.removeEventListener('fiserv-cashapp-cancel', onCancel);
                    buttonElement.removeEventListener('fiserv-cashapp-error', onError);
                    contextElement.removeEventListener('fiserv-context-init-error', onError);
                    contextElement.remove();
                }
            };
        },

        onCashAppApprove: function (generation) {
            if (
                generation !== this.loadGeneration ||
                this.selectedToken() ||
                this.getCode() !== this.isChecked() ||
                !this.isPlaceOrderActionAllowed()
            ) {
                return;
            }

            this.onCashAppSuccess();
        },

        onCashAppCancel: function () {
            globalMessageList.addNoticeMessage({
                message: $t('Cash App checkout was cancelled. Please try again.')
            });
        },

        /**
         * Handle Cash App approval (one-time or newly saved wallet).
         */
        onCashAppSuccess: function () {
            this.additionalData = {
                payment_source: 'cashapp',
                payment_session: this.paymentPayload.sessionId
            };

            this.placeOrder('parent');
        },

        destroyHandle: function (handle) {
            if (handle && typeof handle.destroy === 'function') {
                handle.destroy();
            }
        },

        destroyCashApp: function (invalidate = true) {
            if (invalidate) {
                this.loadGeneration++;
            }
            this.destroyHandle(this.cashAppComponent);
            this.cashAppComponent = null;
            this.cashAppInitialized = false;
            this.initializedAmount = 0.00;
            this.paymentPayload.sessionId = null;
            this.additionalData = {};
            $('#' + CONTAINER_ID).empty();
        },

        restartCashApp: function () {
            this.destroyCashApp();
            if (this.getCode() === this.isChecked()) {
                this.loadCashAppForm();
            }
        },

        getPaymentConfig: function () {
            return window.checkoutConfig?.payment?.[this.getCode()] || {};
        },

        showError: function (error) {
            const message = error?.message || error?.errorCode || error?.description ||
                (typeof error === 'string' ? error : '') || $t('Unable to initialize Cash App Pay.');

            console.error('[Cash App]', error);
            globalMessageList.addErrorMessage({
                message: message
            });
            fullScreenLoader.stopLoader();
        },

        /**
         * Saved token: public_hash only (server hands off to the token composites). Otherwise the Cash App session.
         * @returns {Object}
         */
        getData: function () {
            if (this.selectedToken()) {
                return {
                    method: this.getCode(),
                    additional_data: {
                        public_hash: this.selectedToken(),
                        payment_session: ''
                    }
                };
            }

            return {
                method: this.getCode(),
                additional_data: {
                    payment_session: this.additionalData.payment_session || this.paymentPayload.sessionId,
                    is_active_payment_token_enabler: Boolean(this.canSave && this.savePaymentMethod())
                }
            };
        },

        getCode: function () {
            return this.code;
        },

        showPrivacyStatement: function () {
            return false;
        },

        isBillingAddressRequired: function () {
            return true;
        },

        isActive: function () {
            const active = this.getCode() === this.isChecked();

            this.active(active);

            return active;
        }
    });
});
