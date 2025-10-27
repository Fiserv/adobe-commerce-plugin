define([], function () {
	'use strict';

	return function (maps) {
		let oldRequire = window.require;
		window.require = Object.assign((a,b,c) =>
		{
			if (typeof a === "object" && 
				typeof a[0] !== "undefined" &&
				typeof maps[a[0]] !== "undefined") {
				a[0] = maps[a[0]];
			}
			return oldRequire(a,b,c);
		}, oldRequire);
	};
}
);

