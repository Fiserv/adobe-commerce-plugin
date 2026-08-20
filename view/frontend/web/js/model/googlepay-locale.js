define([], function () {

    /**
     * NOTE on how the CommerceHub Checkout SDK actually resolves button language
     * (reverse-engineered from the shipped `checkout.js` bundle — there is no
     * published reference for this, see AGENTS.md/Docs/Solutioning which only
     * covers Apple Pay):
     *
     * The Google Pay (and other wallet) button's displayed language is driven
     * entirely by the SDK's internal `checkoutContext.locale` getter
     * (`config.customer.locale`). Anything passed as `data.button.buttonLocale`
     * (or similar) directly into `window.fiserv.components.googlePay({...})` is
     * NOT read by the SDK — it's silently ignored dead configuration.
     *
     * The *only* supported way to change it client-side is calling the explicit
     * `setLocale()` method on the session context returned by
     * `window.fiserv.context()`, e.g.:
     *
     *   await window.fiserv.context().setLocale('fr-FR');
     *
     * `setLocale()` validates its argument against the SDK's internal
     * `SupportedLocale` enum using a lookup table keyed by the *full* BCP47 tag
     * (case-insensitive, "_" or "-" separator) — bare language codes like "fr"
     * are NOT valid keys and will throw. It must be one of the exact tags below.
     */
    var DEFAULT_SDK_LOCALE = 'en-US';

    // Full BCP47 tags accepted by SDK v4.1.26's `SupportedLocale` enum / the
    // internal lookup table used by `context.setLocale()`. Confirmed by
    // inspecting the shipped bundle (view/base/requirejs-config.js pins the
    // version). Re-verify this list against the enum if the SDK is upgraded.
    var SUPPORTED_SDK_LOCALES = [
        'en-US', 'en-GB', 'zh-CN', 'zh-TW', 'da-DK', 'nl-NL', 'fy-NL', 'fr-FR',
        'de-DE', 'hi-IN', 'iw-IL', 'it-IT', 'ja-JP', 'ko-KR', 'ms-MY', 'no-NO',
        'fa-IR', 'pl-PL', 'pt-PT', 'es-ES', 'sv-SE', 'uk-UA', 'vi-VN'
    ];

    // Map a 2-letter language code to the specific region-qualified tag the
    // SDK recognizes (the SDK only ships one region variant per language,
    // e.g. "fr" always resolves to "fr-FR" even for fr-CA shoppers).
    var LANGUAGE_TO_SDK_LOCALE = {
        en: 'en-US',
        zh: 'zh-CN',
        da: 'da-DK',
        nl: 'nl-NL',
        fy: 'fy-NL',
        fr: 'fr-FR',
        de: 'de-DE',
        hi: 'hi-IN',
        iw: 'iw-IL',
        he: 'iw-IL', // "he" is the modern ISO code for Hebrew; SDK still uses legacy "iw".
        it: 'it-IT',
        ja: 'ja-JP',
        ko: 'ko-KR',
        ms: 'ms-MY',
        no: 'no-NO',
        nb: 'no-NO',
        nn: 'no-NO',
        fa: 'fa-IR',
        pl: 'pl-PL',
        pt: 'pt-PT',
        es: 'es-ES',
        sv: 'sv-SE',
        uk: 'uk-UA',
        vi: 'vi-VN'
    };

    /**
     * Resolve the Chinese variant: SDK has zh-CN (Simplified) and zh-TW
     * (Traditional). Region/script hints decide which one to use.
     *
     * @param {Array} localeParts - normalized locale segments, e.g. ["zh","Hant","TW"]
     * @returns {string}
     */
    function resolveChineseVariant(localeParts) {
        var region = (localeParts[localeParts.length - 1] || '').toUpperCase();

        if (localeParts.indexOf('Hant') !== -1 || region === 'TW' || region === 'HK') {
            return 'zh-TW';
        }

        return 'zh-CN';
    }

    /**
     * Map an arbitrary BCP47 locale string (e.g. "fr", "fr-FR", "fr-CA",
     * "zh-Hans-CN") to one of the CommerceHub SDK's supported full locale
     * tags, suitable for `context.setLocale()`. Falls back to English
     * ("en-US") when the locale is missing, malformed, or not supported by
     * the currently pinned SDK version.
     *
     * @param {string} locale
     * @returns {string}
     */
    function mapToSdkLocale(locale) {
        if (!locale || typeof locale !== 'string') {
            return DEFAULT_SDK_LOCALE;
        }

        var normalized = locale.replace(/_/g, '-');
        var parts = normalized.split('-').filter(Boolean);
        var language = (parts[0] || '').toLowerCase();

        if (!language) {
            return DEFAULT_SDK_LOCALE;
        }

        // If the caller already supplied a full supported tag (any case/sep),
        // honor it directly instead of collapsing to the language default.
        var exactMatch = SUPPORTED_SDK_LOCALES.filter(function (tag) {
            return tag.toLowerCase() === normalized.toLowerCase();
        })[0];

        if (exactMatch) {
            return exactMatch;
        }

        if (language === 'zh') {
            return resolveChineseVariant(parts);
        }

        return LANGUAGE_TO_SDK_LOCALE[language] || DEFAULT_SDK_LOCALE;
    }

    return {
        DEFAULT_SDK_LOCALE: DEFAULT_SDK_LOCALE,
        SUPPORTED_SDK_LOCALES: SUPPORTED_SDK_LOCALES,
        mapToSdkLocale: mapToSdkLocale
    };
});

