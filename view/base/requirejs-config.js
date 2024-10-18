var config = {
	shim: {
		SDCv2Library : {
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
		SDCv2Library : 'https://commercehub-secure-data-capture.fiservapps.com/3.1.9/checkout',
		commercehubCertClient : 'https://cert.api.fiservapps.com/ch/sdk/v1/commercehub-client-sdk',
		commercehubProdClient : 'https://prod.api.fiservapps.com/ch/sdk/v1/commercehub-client-sdk'
    }
};
