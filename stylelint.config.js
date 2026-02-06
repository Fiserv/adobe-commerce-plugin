export default {
	plugins: ['@stylistic/stylelint-plugin'],
	extends: [
		'stylelint-config-standard',
		'@stylistic/stylelint-config',
	],
	rules: {
		'property-no-vendor-prefix': undefined,
		'selector-class-pattern': undefined,
		'custom-property-pattern': undefined,
		'keyframes-name-pattern': undefined,
		'no-descending-specificity': undefined,
		'selector-pseudo-class-no-unknown': undefined,
		'font-family-no-missing-generic-family-keyword': undefined,
		'@stylistic/max-line-length': undefined,
		'@stylistic/indentation': ['tab'],
		'@stylistic/max-empty-lines': [2],
		'@stylistic/no-empty-first-line': true,
		'@stylistic/no-eol-whitespace': true,
		'@stylistic/no-extra-semicolons': true,
	},
};
