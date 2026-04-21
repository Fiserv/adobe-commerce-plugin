/**
 * Open Refund — Admin Form JS
 *
 * Supports two payment source types:
 *   vault    — existing PaymentToken from the vault
 *   new_card — hosted fields → ch-adapter.submitCardForm() → sessionId
 *              → POST fiserv/openRefund/tokenizeCard (PaymentSession → PaymentToken)
 *              → POST fiserv/openRefund/save with raw tokenData
 *              (CH /payments/v1/refunds does NOT accept PaymentSession)
 */
define([
    'jquery',
    'uiRegistry',
    'Fiserv_Payments/js/ch-adapter',
    'Magento_Ui/js/modal/alert',
    'mage/validation'
], function ($, registry, chAdapter, alert) {
    'use strict';

    // ── Custom validator: 1 or 2 decimal places, no integers ─────────────────
    $.validator.addMethod(
        'validate-currency-amount',
        function (value) {
            if (value === '' || value === null) { return true; }
            return /^\d+\.\d{1,2}$/.test(value.trim());
        },
        $.mage.__('Please enter a valid amount with 1 or 2 decimal places (e.g. 10.99).')
    );

    // ── UI component registry paths ──────────────────────────────────────────
    var FORM_NS          = 'fiserv_open_refund_form.fiserv_open_refund_form';
    var CUSTOMER_COMP    = FORM_NS + '.refund_details.customer_id';
    var TOKEN_COMP       = FORM_NS + '.payment_source.vault_token_hash';
    var SOURCE_TYPE_COMP = FORM_NS + '.payment_source.payment_source_type';

    // ── Module state ─────────────────────────────────────────────────────────
    var fieldsReady   = false;
    var tokenising    = false;
    var paymentConfig = {};
    var storeUrl      = '';

    // ── Helpers ──────────────────────────────────────────────────────────────

    function getFormKey() {
        return $('input[name="form_key"]').val() || '';
    }

    function setTokenOptions(tokens) {
        registry.async(TOKEN_COMP)(function (tokenComp) {
            var koOptions = (!tokens || tokens.length === 0)
                ? [{ value: '', label: '-- No saved cards found --' }]
                : [{ value: '', label: '-- Select a saved card --' }].concat(
                    tokens.map(function (t) { return { value: t.value, label: t.label }; })
                  );

            if (typeof tokenComp.setOptions === 'function') {
                tokenComp.setOptions(koOptions);
            } else {
                tokenComp.options(koOptions);
            }
            tokenComp.value('');
        });
    }

    function showBanner(isSuccess, text) {
        var $banner = $('#open-refund-banner');
        $banner
            .removeClass()
            .addClass(isSuccess ? 'message message-success success' : 'message message-error error')
            .text(text)
            .show();
        $('html, body').animate({ scrollTop: $banner.offset().top - 80 }, 200);
    }

    function showHostedFieldError(msg) {
        showBanner(false, msg || $.mage.__('Card entry error. Please refresh the page.'));
        if (msg) { console.error('[OpenRefund]', msg); }
    }

    // ── Card brand icon ──────────────────────────────────────────────────────

    var BRAND_CLASS_MAP = {
        'visa':             'sdc-card-brand-icon-visa',
        'mastercard':       'sdc-card-brand-icon-mastercard',
        'american-express': 'sdc-card-brand-icon-amex',
        'diners-club':      'sdc-card-brand-icon-diners',
        'discover':         'sdc-card-brand-icon-discover',
        'jcb':              'sdc-card-brand-icon-jcb',
        'unionpay':         'sdc-card-brand-icon-union',
        'maestro':          'sdc-card-brand-icon-maestro',
        'elo':              'sdc-card-brand-icon-elo'
    };

    function onCardBrandChange(brand) {
        var $icon = $('#sdc-card-brand-icon');
        $icon.attr('class', 'sdc-card-brand-icon');
        if (brand && BRAND_CLASS_MAP[brand]) {
            $icon.addClass(BRAND_CLASS_MAP[brand]);
        }
    }

    // ── Field validity (green/red borders + error messages) ──────────────────

    var FIELD_FRAME_IDS = {
        cardNumber:      '#sdc-card-number-frame',
        nameOnCard:      '#sdc-card-name-frame',
        securityCode:    '#sdc-security-code-frame',
        expirationMonth: '#sdc-exp-month-frame',
        expirationYear:  '#sdc-exp-year-frame'
    };

    var FIELD_ERROR_IDS = {
        cardNumber:      '#sdc-card-number-invalid-message',
        nameOnCard:      '#sdc-card-name-invalid-message',
        securityCode:    '#sdc-security-code-invalid-message',
        expirationMonth: '#sdc-exp-month-invalid-message',
        expirationYear:  '#sdc-exp-year-invalid-message'
    };

    function onFieldValidity(data) {
        var field  = data['field'];
        var $frame = $(FIELD_FRAME_IDS[field]);
        var $msg   = $(FIELD_ERROR_IDS[field]);
        if (!$frame.length) { return; }

        if (data['isValid'] === true) {
            $frame.removeClass('sdc-error-field').addClass('sdc-valid-field');
            $msg.addClass('sdc-hidden');
        } else if (data['shouldShowError'] === true) {
            var invalidMessages = paymentConfig['invalidFields'] || {};
            $msg.text(invalidMessages[field] || '');
            $frame.removeClass('sdc-valid-field').addClass('sdc-error-field');
            $msg.removeClass('sdc-hidden');
        } else {
            $frame.removeClass('sdc-valid-field sdc-error-field');
            $msg.addClass('sdc-hidden');
        }
    }

    function onFieldFocus(fieldName) {
        var $frame = $(FIELD_FRAME_IDS[fieldName]);
        if (!$frame.length) { return; }
        if ($frame[0].contains(document.activeElement)) {
            $frame.addClass('sdc-focused-field');
        } else {
            $frame.removeClass('sdc-focused-field');
        }
    }

    // ── Mask / unmask ────────────────────────────────────────────────────────

    function bindMaskToggle() {
        $('#sdc-unmask-number').on('click', function () {
            chAdapter.unmask('cardNumber');
            $('#sdc-unmask-number').addClass('sdc-hidden');
            $('#sdc-mask-number').removeClass('sdc-hidden');
        });
        $('#sdc-mask-number').on('click', function () {
            chAdapter.mask('cardNumber');
            $('#sdc-unmask-number').removeClass('sdc-hidden');
            $('#sdc-mask-number').addClass('sdc-hidden');
        });
        $('#sdc-unmask-security').on('click', function () {
            chAdapter.unmask('securityCode');
            $('#sdc-unmask-security').addClass('sdc-hidden');
            $('#sdc-mask-security').removeClass('sdc-hidden');
        });
        $('#sdc-mask-security').on('click', function () {
            chAdapter.mask('securityCode');
            $('#sdc-unmask-security').removeClass('sdc-hidden');
            $('#sdc-mask-security').addClass('sdc-hidden');
        });
    }

    // ── Hosted fields ────────────────────────────────────────────────────────

    function mountHostedFields() {
        if (fieldsReady) { return; }

        chAdapter.initialize(
            paymentConfig,
            function () {},        // iframeReadyCallback (no-op; processStop handled below)
            function () {},        // iframeValidCallback  (not needed for refund flow)
            onCardBrandChange,
            onFieldValidity,
            onFieldFocus
        );

        $('body').trigger('processStart');
        new Promise(function (resolve, reject) {
            chAdapter.instantiateIframe(resolve, reject);
        }).then(function () {
            $('body').trigger('processStop');
            fieldsReady = true;
            $('#open-refund-hosted-error').hide();
            bindMaskToggle();
        }).catch(function (err) {
            $('body').trigger('processStop');
            showHostedFieldError('Failed to instantiate hosted fields: ' + err);
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
                if (!customerId) { setTokenOptions([]); return; }

                fetch(
                    getTokensUrl
                        + (getTokensUrl.indexOf('?') === -1 ? '?' : '&')
                        + 'customer_id=' + encodeURIComponent(customerId)
                        + '&form_key=' + encodeURIComponent(getFormKey()),
                    { credentials: 'include' }
                )
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

    // ── Save flows ───────────────────────────────────────────────────────────

    function doAjaxSave(saveUrl, indexUrl, extraData) {
        registry.async('fiserv_open_refund_form.fiserv_open_refund_form_data_source')(function (provider) {
            var data = $.extend({}, provider.get('data') || {}, extraData || {});
            data.form_key = getFormKey();

            $.ajax({
                url:        saveUrl,
                type:       'POST',
                data:       data,
                dataType:   'json',
                showLoader: true
            }).done(function (response) {
                if (response && response.error) {
                    showBanner(false, $.mage.__('Error') + ': ' + (response.message || 'An error occurred.'));
                    // Re-arm hosted fields so the admin can retry
                    if ((extraData || {}).payment_source_type === 'new_card') {
                        fieldsReady = false;
                        $('#sdc-unmask-number, #sdc-mask-number, #sdc-unmask-security, #sdc-mask-security').off('click');
                        mountHostedFields();
                    }
                } else {
                    try { sessionStorage.setItem('openRefundSuccess', $.mage.__('Open refund submitted successfully.')); } catch (e) {}
                    window.location.href = indexUrl;
                }
            }).fail(function () {
                showBanner(false, $.mage.__('An unexpected error occurred. Please try again.'));
            });
        });
    }

    /**
     * New-card save flow:
     *   1. ch-adapter.submitCardForm() → sessionId  (hosted fields → PaymentSession)
     *   2. POST tokenizeCardUrl         → tokenData  (PaymentSession → PaymentToken)
     *   3. doAjaxSave with tokenData    → Save.php   (PaymentToken → CH refund)
     */
    function doNewCardSave(saveUrl, indexUrl, tokenizeCardUrl) {
        if (!fieldsReady) {
            showHostedFieldError($.mage.__('Card entry form is not ready. Please refresh the page.'));
            return;
        }
        if (tokenising) { return; }
        tokenising = true;

        $('body').trigger('processStart');

        chAdapter.submitCardForm(
            storeUrl,
            function (sessionId) {
                // Step 1 succeeded — now tokenize server-side
                $.ajax({
                    url:      tokenizeCardUrl,
                    type:     'POST',
                    data:     { session_id: sessionId, form_key: getFormKey() },
                    dataType: 'json'
                }).done(function (res) {
                    tokenising = false;
                    $('body').trigger('processStop');

                    if (!res || res.error) {
                        showBanner(false, $.mage.__('Card tokenisation failed: ') + (res && res.message ? res.message : ''));
                        fieldsReady = false;
                        $('#sdc-unmask-number, #sdc-mask-number, #sdc-unmask-security, #sdc-mask-security').off('click');
                        mountHostedFields();
                        return;
                    }

                    // Step 2 succeeded — submit the refund with raw PaymentToken data
                    doAjaxSave(saveUrl, indexUrl, {
                        payment_source_type:   'new_card',
                        new_card_token_data:   res.tokenData   || '',
                        new_card_token_source: res.tokenSource || '',
                        new_card_exp_month:    res.expMonth    || '',
                        new_card_exp_year:     res.expYear     || '',
                        new_card_name_on_card: res.nameOnCard  || ''
                    });
                }).fail(function () {
                    tokenising = false;
                    $('body').trigger('processStop');
                    showBanner(false, $.mage.__('An unexpected error occurred during card tokenisation. Please try again.'));
                });
            },
            function (err) {
                tokenising = false;
                $('body').trigger('processStop');
                console.error('[OpenRefund] submitCardForm failed:', err);
                showHostedFieldError($.mage.__('Card capture failed. Please re-enter your card details.'));
                fieldsReady = false;
                $('#sdc-unmask-number, #sdc-mask-number, #sdc-unmask-security, #sdc-mask-security').off('click');
                mountHostedFields();
            }
        );
    }

    function initSaveWrapper(saveUrl, indexUrl, tokenizeCardUrl) {
        registry.async(FORM_NS)(function (formComp) {
            formComp.save = function () {
                formComp.validate();
                if (formComp.additionalInvalid || (formComp.source && formComp.source.get('params.invalid'))) {
                    return;
                }

                registry.async(SOURCE_TYPE_COMP)(function (sourceComp) {
                    if (sourceComp.value() === 'new_card') {
                        doNewCardSave(saveUrl, indexUrl, tokenizeCardUrl);
                    } else {
                        doAjaxSave(saveUrl, indexUrl, { payment_source_type: 'vault' });
                    }
                });
            };
        });
    }

    // ── Entry point ──────────────────────────────────────────────────────────
    return function (config) {
        paymentConfig = config.paymentConfig || {};
        storeUrl      = paymentConfig.storeUrl || '';

        initCustomerChangeListener(config.getTokensUrl || '');
        initSourceTypeToggle();
        $(function () {
            initSaveWrapper(
                config.saveUrl         || '',
                config.indexUrl        || '',
                config.tokenizeCardUrl || ''
            );
        });
    };
});

