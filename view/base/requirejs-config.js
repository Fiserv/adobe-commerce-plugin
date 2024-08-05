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
	SDCv2Library : 'https://commercehub-secure-data-capture.fiservapps.com/2.2.0/saq-a',
        commercehubCertClient : 'https://cert.api.fiservapps.com/ch/sdk/v1/commercehub-client-sdk',
        commercehubProdClient : 'https://prod.api.fiservapps.com/ch/sdk/v1/commercehub-client-sdk'
    }
};
