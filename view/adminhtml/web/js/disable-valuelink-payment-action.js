define(['jquery'], ($) => {
	const creditDebitSelector = '[name="groups[fiserv_payments][groups][credit_debit_cards][fields][payment_action][value]"]';
	const valuelinkSelector = '[name="groups[fiserv_payments][groups][valuelink_gift_card][fields][payment_action][value]"]';

	function toggleValuelink() {
		const creditValue = $(creditDebitSelector).val();
		const $valuelink = $(valuelinkSelector);
		const hiddenInputName = $valuelink.attr('name');
		const $hidden = $(`input[type="hidden"][name="${hiddenInputName}"]`);

		if (creditValue === 'authorize') {
			$valuelink.prop('disabled', false);
			$hidden.remove();
		} else {
			$valuelink.val('authorize_capture');
			/* This hidden field is need to update the database as disabled fields do not get submitted */
			if ($hidden.length === 0) {
				$('<input>')
					.attr({
						type: 'hidden',
						name: hiddenInputName,
						value: 'authorize_capture',
					})
					.insertAfter($valuelink);
			}
			$valuelink.prop('disabled', true);
		}
	}

	$(document).on('change', creditDebitSelector, toggleValuelink);
	$(document).ready(toggleValuelink);
});
