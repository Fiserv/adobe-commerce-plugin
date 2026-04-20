/**
 * Open Refund — Admin Form JS
 *
 * Responsibilities:
 *  1. Customer → vault token AJAX reload
 *  2. Payment source type toggle (vault / new_card)
 *  3. Mount CommerceHub hosted field iframes (new card path)
 *  4. Wrap form.save() so hosted-fields tokenisation happens BEFORE the
 *     Magento UI provider POSTs to the server.  The hidden UI field
 *     "hosted_field_token" (defined in the UI form XML) carries the token
 *     in the normal KO data scope, so it is included in the AJAX POST body.
 */
define([
    'jquery',
    'uiRegistry',
    'Magento_Ui/js/modal/alert',
    'mage/validation'
], function ($, registry, alert) {
    'use strict';

    // ── Custom validator: 1 or 2 decimal places, no integers ─────────────────
    $.validator.addMethod(
        'validate-currency-amount',
        function (value) {
            if (value === '' || value === null) {
                return true; // let required-entry handle empty
            }
            return /^\d+\.\d{1,2}$/.test(value.trim());
        },
        $.mage.__('Please enter a valid amount with 1 or 2 decimal places (e.g. 10.99).')
    );

    // ── UI component registry paths ──────────────────────────────────────────
    var FORM_NS            = 'fiserv_open_refund_form.fiserv_open_refund_form';
    var CUSTOMER_COMP      = FORM_NS + '.refund_details.customer_id';
    var TOKEN_COMP         = FORM_NS + '.payment_source.vault_token_hash';
    var SOURCE_TYPE_COMP   = FORM_NS + '.payment_source.payment_source_type';
    var HOSTED_TOKEN_COMP  = FORM_NS + '.payment_source.hosted_field_token';

    // ── Module state ─────────────────────────────────────────────────────────
    var sdk         = null;
    var fieldsReady = false;
    var tokenising  = false;   // guard against re-entrant save calls

    // ── Helpers ──────────────────────────────────────────────────────────────

    function getFormKey() {
        return $('input[name="form_key"]').val() || '';
    }

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

            if (typeof tokenComp.setOptions === 'function') {
                tokenComp.setOptions(koOptions);
            } else {
                tokenComp.options(koOptions);
            }

            tokenComp.value('');
        });
    }

    function showHostedFieldError(msg) {
        $('#open-refund-hosted-error').show();
        if (msg) {
            console.error('[OpenRefund]', msg);
        }
    }

    function mountHostedFields() {
        if (fieldsReady) { return; }

        require(['Fiserv_Payments/js/ch-adapter'], function (chAdapter) {
            try {
                chAdapter.initialize(
                    {},
                    function () { fieldsReady = true; },
                    function () {},
                    function () {},
                    function () {},
                    function () {}
                );
                sdk = chAdapter;
                fieldsReady = true;
            } catch (e) {
                showHostedFieldError('mountHostedFields error: ' + e);
            }
        }, function (err) {
            showHostedFieldError('Failed to load ch-adapter: ' + err);
        });
    }

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

                sourceComp.value.subscribe(applySourceType);
                applySourceType(sourceComp.value());
            });
        });
    }

    function initCustomerChangeListener(getTokensUrl) {
        registry.async(CUSTOMER_COMP)(function (customerComp) {
            customerComp.value.subscribe(function (customerId) {
                if (!customerId) {
                    setTokenOptions([]);
                    return;
                }

                var url = getTokensUrl
                    + (getTokensUrl.indexOf('?') === -1 ? '?' : '&')
                    + 'customer_id=' + encodeURIComponent(customerId)
                    + '&form_key=' + encodeURIComponent(getFormKey());

                fetch(url, { credentials: 'include' })
                    .then(function (r) {
                        if (!r.ok) { throw new Error('HTTP ' + r.status); }
                        return r.json();
                    })
                    .then(function (data) { setTokenOptions(data.tokens || []); })
                    .catch(function (err) {
                        console.error('[OpenRefund] Token reload failed:', err);
                        setTokenOptions([]);
                    });
            });
        });
    }

    /**
     * Show a Magento-style banner above the form.
     */
    function showBanner(isSuccess, text) {
        var cssClass = isSuccess
            ? 'message message-success success'
            : 'message message-error error';

        var $banner = $('#open-refund-banner');
        $banner
            .removeClass()
            .addClass(cssClass)
            .text(text)
            .show();

        $('html, body').animate({ scrollTop: $banner.offset().top - 80 }, 200);
    }

    /**
     * Post form data directly via $.ajax — never touches the native form action.
     */
    function doAjaxSave(saveUrl, indexUrl, extraData) {
        var PROVIDER = 'fiserv_open_refund_form.fiserv_open_refund_form_data_source';

        registry.async(PROVIDER)(function (provider) {
            var data = provider.get('data') || {};

            // Merge any extra fields (e.g. hosted_field_token from tokenisation)
            if (extraData) {
                $.extend(data, extraData);
            }

            data.form_key = getFormKey();

            $.ajax({
                url:         saveUrl,
                type:        'POST',
                data:        data,
                dataType:    'json',
                showLoader:  true
            }).done(function (response) {
                if (response && response.error) {
                    var errMsg = typeof response.message === 'string'
                        ? response.message : 'An error occurred.';
                    showBanner(false, $.mage.__('Error') + ': ' + errMsg);
                } else {
                    try {
                        sessionStorage.setItem(
                            'openRefundSuccess',
                            $.mage.__('Open refund submitted successfully.')
                        );
                    } catch (e) {}
                    window.location.href = indexUrl;
                }
            }).fail(function () {
                showBanner(false, $.mage.__('An unexpected error occurred. Please try again.'));
            });
        });
    }

    /**
     * Intercept the UI form's save() so we can:
     *  - tokenise hosted fields first (new_card path)
     *  - then call doAjaxSave() instead of letting Magento navigate
     */
    function initSaveWrapper(saveUrl, indexUrl) {
        registry.async(FORM_NS)(function (formComp) {
            formComp.save = function () {
                // Run Magento's own client-side validation
                formComp.validate();
                if (formComp.additionalInvalid || (formComp.source && formComp.source.get('params.invalid'))) {
                    return;
                }

                registry.async(SOURCE_TYPE_COMP)(function (sourceComp) {
                    var sourceType = sourceComp.value();

                    if (sourceType !== 'new_card') {
                        doAjaxSave(saveUrl, indexUrl);
                        return;
                    }

                    // new_card — tokenise first
                    if (!sdk || !fieldsReady) {
                        showHostedFieldError('Hosted fields are not ready. Please refresh.');
                        return;
                    }

                    if (tokenising) { return; }
                    tokenising = true;

                    sdk.submitCardForm(
                        '',
                        function (sessionToken) {
                            tokenising = false;
                            doAjaxSave(saveUrl, indexUrl, { hosted_field_token: sessionToken });
                        },
                        function () {
                            tokenising = false;
                            showHostedFieldError('Card tokenisation failed. Please re-enter card details.');
                        }
                    );
                });
            };
        });
    }

    // ── Entry point (called by x-magento-init) ───────────────────────────────
    return function (config) {
        var getTokensUrl = config.getTokensUrl || '';
        var saveUrl      = config.saveUrl      || '';
        var indexUrl     = config.indexUrl     || '';

        initCustomerChangeListener(getTokensUrl);
        initSourceTypeToggle();

        $(function () {
            initSaveWrapper(saveUrl, indexUrl);
        });
    };
});

