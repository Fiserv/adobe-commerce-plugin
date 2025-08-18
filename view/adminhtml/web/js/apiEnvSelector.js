define([
    'jquery'
], function ($) {
    'use strict';

    return function (config) {
        $(document).ready(function() {
            var $developerModeField = $('#payment_fiserv_commercehub_developer_mode_active');
            var $apiEnvironmentField = $('#payment_fiserv_commercehub_api_environment');
            var devOptionValue = 'DEV';
            var qaOptionValue = 'QA';

            function updateApiEnvironmentOptions() {
                var developerModeActive = $developerModeField.val();

                if (developerModeActive === "1") {
                    if (!$apiEnvironmentField.find('option[value="' + devOptionValue + '"]').length) {
                        $apiEnvironmentField.append('<option value="' + devOptionValue + '">' + devOptionValue + '</option>');
                    }
                    if (!$apiEnvironmentField.find('option[value="' + qaOptionValue + '"]').length) {
                        $apiEnvironmentField.append('<option value="' + qaOptionValue + '">' + qaOptionValue + '</option>');
                    }
                } else {
                    $apiEnvironmentField.find('option[value="' + devOptionValue + '"]').remove();
                    $apiEnvironmentField.find('option[value="' + qaOptionValue + '"]').remove();
                }
            }

            updateApiEnvironmentOptions();
            $developerModeField.change(updateApiEnvironmentOptions);
        });
    };
});
