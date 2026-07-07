/* eslint-disable unicorn/filename-case, prefer-arrow-callback, unicorn/prefer-module, @stylistic/max-len */
define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list',
    ],
    function (Component, rendererList) {

        const config = (globalThis.checkoutConfig && globalThis.checkoutConfig.payment)
            ? globalThis.checkoutConfig.payment : {};
        const ebtOnlineCode = 'fiserv_ebt_online';

        if (config[ebtOnlineCode] && config[ebtOnlineCode].isActive) {
            rendererList.push({
                type: ebtOnlineCode,
                component: 'Fiserv_Payments/js/view/payment/method-renderer/commercehub-ebt-online',
            });
        }

        return Component.extend({});
    },
);

