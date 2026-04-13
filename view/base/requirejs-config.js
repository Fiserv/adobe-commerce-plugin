var config = {
	shim: {
		SDCv2Library : {
			exports : 'Fiserv'
		},
		commercehubQaClient: {
			exports : 'Fiserv'
		},
		commercehubCertClient: {
			exports : 'Fiserv'
		},
		commercehubProdClient: {
			exports : 'Fiserv'
		}
	},
	
    paths : {
		SDCv2Library : 'https://commercehub-checkout.fiservapps.com/sdk/3.8.9/checkout',
		commercehubQaClient : 'https://qa.api.fiservapps.com/ch/sdk/v1/commercehub-client-sdk',
		commercehubCertClient : 'https://cert.api.fiservapps.com/ch/sdk/v1/commercehub-client-sdk',
		commercehubProdClient : 'https://prod.api.fiservapps.com/ch/sdk/v1/commercehub-client-sdk',
		'fastlane/axo.min' : 'https://www.paypalobjects.com/connect-boba/axo.min',
		'fastlane/axo' : 'https://www.paypalobjects.com/connect-boba/axo',
		'chBraintreeClient' : 'https://js.braintreegateway.com/web/3.128.0/js/client.min',
		'ch-braintree-hosted-fields' : 'https://js.braintreegateway.com/web/3.128.0/js/hosted-fields.min'
    },
};
