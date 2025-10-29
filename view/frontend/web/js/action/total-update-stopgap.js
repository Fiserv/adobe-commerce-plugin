define(
	[
		'uiComponent',
		'Magento_Checkout/js/model/quote'
	],
	function (
		Component,
		quote
	) {
		'use strict';
		
		return Component.extend({
			initialize: function () {
				this._super();

				quote.totals.subscribe((totals) => {
					if (totals && totals['grand_total']) {
						location.reload();
					}
				});
			}
		});
	}
);
