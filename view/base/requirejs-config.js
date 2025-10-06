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
		SDCv2Library : 'https://cdn-nonprod.fiservapps.com/ch/secure-data-capture/3.5.3/checkout',
		commercehubQaClient : 'https://qa.api.fiservapps.com/ch/sdk/v1/commercehub-client-sdk',
		commercehubCertClient : 'https://cert.api.fiservapps.com/ch/sdk/v1/commercehub-client-sdk',
		commercehubProdClient : 'https://prod.api.fiservapps.com/ch/sdk/v1/commercehub-client-sdk'
    },
};
