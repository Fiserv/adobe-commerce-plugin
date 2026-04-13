/**
 * Shared subscription frequency map.
 * Used by both commercehub-form.js and commercehub-vault.js to avoid duplication.
 *
 * Each key is the frequency string sent to the server; the value contains the
 * interval_value (int) and interval_unit (string) stored in the subscription row.
 */
define([], function () {
    'use strict';

    return {
        'minute':     { value: 1, unit: 'minute' },
        'weekly':     { value: 1, unit: 'week'   },
        'biweekly':   { value: 2, unit: 'week'   },
        'monthly':    { value: 1, unit: 'month'  },
        '2months':    { value: 2, unit: 'month'  },
        'quarterly':  { value: 3, unit: 'month'  },
        'semiannual': { value: 6, unit: 'month'  },
        'yearly':     { value: 1, unit: 'year'   }
    };
});

