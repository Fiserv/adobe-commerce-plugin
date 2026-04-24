define([], function() {
	'use strict';

	return function ensureArrayEntriesSupport() {
		if (typeof Array.prototype.entries === 'function') {
			return;
		}

		Array.prototype.entries = function () {
			var index = 0;
			var source = this;

			var iterator = {
				next: function () {
					if (index < source.length) {
						return { value: [index, source[index++]], done: false };
					}

					return { value: undefined, done: true };
				}
			};

			if (typeof Symbol !== 'undefined' && Symbol.iterator) {
				iterator[Symbol.iterator] = function () {
					return iterator;
				};
			}

			return iterator;
		};
	};
});


