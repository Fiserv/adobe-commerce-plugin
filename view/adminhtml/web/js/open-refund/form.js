/**
 * Open Refund — Admin Form JS
 *
 * Responsibilities:
 *  1. Customer → vault token AJAX reload
 *  2. Payment source type toggle (vault / new_card)
 *  3. Mount CommerceHub hosted field iframes (new card path)
 *  4. Intercept form submit on new card path to tokenise first
 */
define([
    'jquery',
    'uiRegistry'
], function ($, registry) {
    'use strict';

    // ── UI component registry paths ──────────────────────────────────────────
    var FORM_NS          = 'fiserv_open_refund_form.fiserv_open_refund_form';
    var CUSTOMER_COMP    = FORM_NS + '.refund_details.customer_id';
    var TOKEN_COMP       = FORM_NS + '.payment_source.vault_token_hash';
    var SOURCE_TYPE_COMP = FORM_NS + '.payment_source.payment_source_type';

    // ── Module state ─────────────────────────────────────────────────────────
    var sdk         = null;
    var fieldsReady = false;
    var submitting  = false;

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Read the Magento form_key from the DOM (admin session token).
     * This is required for some admin AJAX endpoints even on GET requests.
     */
    function getFormKey() {
        return $('input[name="form_key"]').val() || '';
    }

    /**
     * Update the vault_token_hash KO select component with a new list of tokens.
     * We do NOT touch the DOM directly — KO owns those <option> elements and will
     * overwrite any direct DOM changes on its next render cycle.
     *
     * @param {Array} tokens  [{value, label}, ...]
     */
    function setTokenOptions(tokens) {
        registry.async(TOKEN_COMP)(function (tokenComp) {
            var koOptions;

            if (!tokens || tokens.length === 0) {
                koOptions = [{ value: '', label: '-- No saved cards found --' }];
            } else {
                koOptions = [{ value: '', label: '-- Select a saved card --' }];
                $.each(tokens, function (i, token) {
                    koOptions.push({ value: token.value, label: token.label });
                });
            }

            console.log('[OpenRefund] setTokenOptions:', koOptions);

            if (typeof tokenComp.setOptions === 'function') {
                tokenComp.setOptions(koOptions);
            } else {
                // Fallback: update the options observable directly
                tokenComp.options(koOptions);
            }

            // Reset the currently-selected value so the placeholder shows
            tokenComp.value('');
        });
    }

    /**
     * Show the hosted-fields error banner.
     */
    function showHostedFieldError() {
        $('#open-refund-hosted-error').show();
    }

    /**
     * Mount CommerceHub hosted field iframes once.
     * Guarded by the `fieldsReady` flag so iframes only mount once.
     */
    function mountHostedFields() {
        if (fieldsReady) {
            return;
        }

        require(['Fiserv_Payments/js/ch-adapter'], function (chAdapter) {
            try {
                chAdapter.initialize(
                    /* paymentConfig  */ {},
                    /* successCb      */ function () { fieldsReady = true; },
                    /* validCb        */ function () {},
                    /* brandChangeCb  */ function () {},
                    /* fieldValidityCb*/ function () {},
                    /* fieldFocusCb   */ function () {}
                );
                sdk = chAdapter;
                fieldsReady = true;
            } catch (e) {
                console.error('[OpenRefund] mountHostedFields error:', e);
                showHostedFieldError();
            }
        }, function (err) {
            console.error('[OpenRefund] Failed to load ch-adapter:', err);
            showHostedFieldError();
        });
    }

    /**
     * Wire up the payment-source-type toggle.
     * vault    → show token select, hide hosted fields
     * new_card → hide token select, show hosted fields & mount iframes
     */
    function initSourceTypeToggle() {
        registry.async(SOURCE_TYPE_COMP)(function (sourceComp) {
            registry.async(TOKEN_COMP)(function (tokenComp) {

                function applySourceType(value) {
                    if (value === 'new_card') {
                        tokenComp.visible(false);
                        $('#hosted-fields-wrap').show();
                        mountHostedFields();
                    } else {
                        tokenComp.visible(true);
                        $('#hosted-fields-wrap').hide();
                    }
                }

                sourceComp.value.subscribe(function (newValue) {
                    applySourceType(newValue);
                });

                applySourceType(sourceComp.value());
            });
        });
    }

    /**
     * Watch the customer_id UI component's KO value observable directly.
     *
     * We do NOT use a DOM 'change' event because Magento may render customer_id
     * as a ui-select (searchable dropdown) rather than a plain <select>, and
     * ui-select does not fire a standard DOM change event.
     *
     * registry.async() resolves as soon as the KO component is instantiated,
     * regardless of the underlying DOM element type.
     *
     * @param {string} getTokensUrl
     */
    function initCustomerChangeListener(getTokensUrl) {
        registry.async(CUSTOMER_COMP)(function (customerComp) {
            console.log('[OpenRefund] customer_id component found:', customerComp.name);

            // Subscribe to future value changes
            customerComp.value.subscribe(function (customerId) {
                console.log('[OpenRefund] Customer changed to:', customerId);

                if (!customerId) {
                    setTokenOptions([]);
                    return;
                }

                var url = getTokensUrl
                    + (getTokensUrl.indexOf('?') === -1 ? '?' : '&')
                    + 'customer_id=' + encodeURIComponent(customerId)
                    + '&form_key=' + encodeURIComponent(getFormKey());

                console.log('[OpenRefund] Fetching tokens from:', url);

                fetch(url, { credentials: 'include' })
                    .then(function (response) {
                        console.log('[OpenRefund] GetTokens status:', response.status);
                        if (!response.ok) {
                            throw new Error('HTTP ' + response.status);
                        }
                        return response.json();
                    })
                    .then(function (data) {
                        console.log('[OpenRefund] Tokens received:', data);
                        setTokenOptions(data.tokens || []);
                    })
                    .catch(function (err) {
                        console.error('[OpenRefund] Token reload failed:', err);
                        setTokenOptions([]);
                    });
            });
        });
    }

    /**
     * Intercept form submit on the new_card path:
     *   1. Prevent default submit
     *   2. Call sdk.submitCardForm() to tokenise the card
     *   3. Inject the session token into #hosted_field_token
     *   4. Re-submit the form
     */
    function initSubmitInterceptor() {
        $(document).on('submit', 'form[data-ui-id]', function (e) {
            var sourceType = $('select[name*="payment_source_type"]').val();

            if (sourceType !== 'new_card') {
                return;
            }

            if (submitting) {
                return;
            }

            e.preventDefault();
            var $form = $(this);

            if (!sdk || !fieldsReady) {
                showHostedFieldError();
                return;
            }

            sdk.submitCardForm(
                '',
                function (sessionToken) {
                    $('#hosted_field_token').val(sessionToken);
                    submitting = true;
                    $form.trigger('submit');
                },
                function () {
                    showHostedFieldError();
                }
            );
        });
    }

    // ── Entry point (called by x-magento-init) ───────────────────────────────
    return function (config /*, element */) {
        var getTokensUrl = config.getTokensUrl || '';
        console.log('[OpenRefund] form.js initialised, getTokensUrl:', getTokensUrl);

        // registry.async handles its own timing — no need for $(function() {...})
        initCustomerChangeListener(getTokensUrl);
        initSourceTypeToggle();

        // Submit interceptor needs DOM — wrap in DOM-ready
        $(function () {
            initSubmitInterceptor();
        });
    };
});

