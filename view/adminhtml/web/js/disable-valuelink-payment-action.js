define(['jquery'], function ($) {
	var creditDebitSelector = '[name="groups[fiserv_payments][groups][credit_debit_cards][fields][payment_action][value]"]';
	var valuelinkSelector = '[name="groups[fiserv_payments][groups][valuelink_gift_card][fields][payment_action][value]"]';

	function toggleValuelink() {
		var creditValue = $(creditDebitSelector).val();
		var $valuelink = $(valuelinkSelector);
		var hiddenInputName = $valuelink.attr('name');
		var $hidden = $('input[type="hidden"][name="' + hiddenInputName + '"]');

		if (creditValue !== 'authorize') {
			$valuelink.val('authorize_capture');
			/* This hidden field is need to update the database as disabled fields do not get submitted */
			if ($hidden.length === 0) {
				$('<input>')
				.attr({
					type: 'hidden',
					name: hiddenInputName,
					value: 'authorize_capture'
				})
				.insertAfter($valuelink);
			}
			$valuelink.prop('disabled', true);
		} else {
			$valuelink.prop('disabled', false);
			$hidden.remove();
		}
	}

	$(document).on('change', creditDebitSelector, toggleValuelink);
	$(document).ready(toggleValuelink);
});
