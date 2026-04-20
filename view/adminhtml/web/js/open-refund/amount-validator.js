/**
 * Custom validation rule: value must be a valid monetary amount
 * with exactly 1 or 2 decimal places (no integers, no >2 dp).
 *
 * Valid:   10.99  |  10.9  |  0.01
 * Invalid: 10     |  10.999
 */
define(['jquery'], function ($) {
    'use strict';

    return function () {
        $.validator.addMethod(
            'validate-currency-amount',
            function (value) {
                if (value === '' || value === null) {
                    return true; // let required-entry handle empty
                }
                // Must match: digits, a dot, then 1 or 2 digits only
                return /^\d+\.\d{1,2}$/.test(value.trim());
            },
            $.mage.__('Please enter a valid amount with 1 or 2 decimal places (e.g. 10.99).')
        );

        return {};
    };
});

