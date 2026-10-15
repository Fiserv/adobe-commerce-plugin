<?php
namespace Fiserv\Payments\Model\AffirmVirtualCard;

/** Remove VCN/card credentials before responses reach payment handlers or failure persistence. */
class ResponseFilter
{
	private const OMIT = [
		'card', 'carddetails', 'virtualcard', 'vcn', 'paymenttokens', 'storedcredentials',
		'pan', 'cardnumber', 'accountnumber', 'primaryaccountnumber', 'cvv', 'cvv2', 'cvc',
		'cvc2', 'cid', 'securitycode', 'expiration', 'expiry', 'expirationdate',
		'expirydate', 'expirationmonth', 'expirationyear', 'expmonth', 'expyear',
		'sdkdata', 'accesstoken', 'apikey', 'apisecret', 'tokendata',
	];

	public function filter(array $response): array
	{
		$filtered = [];
		foreach ($response as $key => $value) {
			$normalized = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string) $key));
			if (in_array($normalized, self::OMIT, true)) {
				continue;
			}
			$filtered[$key] = is_array($value) ? $this->filter($value) : $value;
		}
		return $filtered;
	}
}

