/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
define([
    'ko'
], function (ko) {
    'use strict';

    var initialLocale =
        window.checkoutConfig &&
        window.checkoutConfig.payment &&
        window.checkoutConfig.payment.fiserv_payments &&
        window.checkoutConfig.payment.fiserv_payments.locale || 'en-US';

    return {
        /**
         * Current supported BCP-47 locale resolved by ConfigProvider.
         */
        current: ko.observable(initialLocale)
    };
});

