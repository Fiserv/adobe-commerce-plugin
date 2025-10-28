define(
	[
		'jquery',
		'mage/url'
	],
	function (
		$,
		urlBuilder
	) {
		'use strict';
		
 		return async function (params) {
			let serviceUrl = 'fiserv/commercehub/getcredentials'

			try {
				let response = await fetch(urlBuilder.build(serviceUrl), {
					method: 'POST',
					headers: { 
						'Content-Type': 'application/json',
						'X-Requested-With' : 'XMLHttpRequest'	
					},
					body: JSON.stringify(params),
					credentials: 'same-origin'
				});
				
				if (!response.ok)
				{
					throw new Error("Credentials request failure");
				}
				return await response.json();
			
			} catch (error) {
				throw new Error("An error occurred while creating Commercehub payment session.");
			}
		};
	}
);
