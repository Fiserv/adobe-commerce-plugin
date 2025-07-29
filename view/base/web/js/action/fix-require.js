define([], function () {
        'use strict';
		let maps = {
			'braintree/client.min' : 'ch-braintree-client',
			'braintree/hosted-fields.min' : 'ch-braintree-hosted-fields'
		};

        return function (params) {
			let oldRequire = window.require;
			window.require = (a,b,c) =>
			{
				if (typeof a === "object" && 
					typeof a[0] !== "undefined" &&
					typeof maps[a[0]] !== "undefined") {
					a[0] = maps[a[0]];
				}
				return oldRequire(a,b,c);
			}
		};
    }
);
