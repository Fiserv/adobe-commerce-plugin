define([
	'uiComponent'
], function (Component) {
	'use strict';
	return function (target) {
		return target.extend({
			initialize: function () {
				this._super();
				//if (window.checkoutConfig && window.checkoutConfig.myConfigActive) {
					this.template = 'Fiserv_Payments/form/element/custom-email';
				//}
				return this;
			}
		});
	};
});
