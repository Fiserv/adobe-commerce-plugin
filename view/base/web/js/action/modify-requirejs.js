define([], () => {
	'use strict';

	return function (maps) {
		const oldRequire = globalThis.require;

		globalThis.require = Object.assign((a, b, c) => {
			if (typeof a === 'object' &&
				a[0] !== undefined &&
				maps[a[0]] !== undefined) {
				a[0] = maps[a[0]];
			}

			return oldRequire(a, b, c);
		}, oldRequire);
	};
});
