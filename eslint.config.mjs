import { getEslintConfig } from "@commercehub/frontend-node-tools/index.mjs";

export default getEslintConfig({
	include: {
		JS: [
			"view/**/web/**/*.js", // view/frontend/web and view/adminhtml/web JS
			"*.js"
		],
		CSS: ["view/**/web/**/*.css", "**/*.css"],
		HTML: ["view/**/templates/**/*.html", "**/*.html"],
		JSX: ["**/*.jsx"],
		TS: ["**/*.ts"],
		TSX: ["**/*.tsx"]
	},
	ignore: [
		"coverage",
		"node_modules",
		"build",
		"build-plugins",
		"cdn",
		"src/*.d.ts",
		"vendor",
		"pub",
		"generated"
	],
	overrides: [
		{
			include: ["view/**/web/**/*.js", "*.js"],
			config: {
				languageOptions: {
					globals: {
						$: "readonly",
						jQuery: "readonly",
						define: "readonly", // RequireJS AMD define used by Magento
						require: "readonly"
					}
				},
				rules: {
					"commercehub/no-pointless-if-for": "off",
					"commercehub/no-string-to-string": "off",
					"commercehub/no-duplicate-branch-condition": "off",
					"commercehub/no-dynamic-type-import": "off",
					"commercehub/no-instance-constant": "off",
					"commercehub/no-interface-merging": "off",
					"commercehub/no-redundant-method-names": "off",
					"commercehub/no-strict-null-comparisons": "off",
					"commercehub/prefer-nullish-coalescing": "off",
					"commercehub/prefer-object-values-for-enum": "off",
					"commercehub/repetitive-field-access": "off",
					"commercehub/string-duplication": "off",
					"commercehub/type-only-imports": "off",
					"@stylistic/indent": ["error", 4],
					"@stylistic/operator-linebreak": ["error", "after"],
					"@stylistic/brace-style": ["error", "1tbs", { allowSingleLine: true }]
				}
			}
		},
		{
			include: [
				"view/**/web/js/*.js",
				"view/**/web/js/**/*.js"
			],
			config: {
				rules: {
					"import/no-unused-modules": "off"
				}
			}
		}
	]
});

