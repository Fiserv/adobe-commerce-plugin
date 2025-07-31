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
		SDCv2Library : 'https://commercehub-secure-data-capture.fiservapps.com/3.2.7/checkout',
		commercehubQaClient : 'https://qa.api.fiservapps.com/ch/sdk/v1/commercehub-client-sdk',
		commercehubCertClient : 'https://cert.api.fiservapps.com/ch/sdk/v1/commercehub-client-sdk',
		commercehubProdClient : 'https://prod.api.fiservapps.com/ch/sdk/v1/commercehub-client-sdk',
		'fastlane/axo.min' : 'https://www.paypalobjects.com/connect-boba/axo.min',
		'ch-braintree-client' : 'https://js.braintreegateway.com/web/3.116.2/js/client.min',
		'ch-braintree-hosted-fields' : 'https://js.braintreegateway.com/web/3.116.2/js/hosted-fields.min'
    }
};
