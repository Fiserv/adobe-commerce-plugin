define([
	'jquery'
], function ($) {
	'use strict';

	return function () {
		$(() => {
			const $sourceType = $('#source_type');

			if (!$sourceType.length) {
				return;
			}

			const toggleSource = () => {
				const value = $sourceType.val();
				$('#tokenized-section').toggle(value === 'TOKEN');
				$('#or-card-section').toggle(value === 'HOSTED_FIELDS');
			};

			$sourceType.on('change', toggleSource);
			toggleSource();
		});
	};
});

