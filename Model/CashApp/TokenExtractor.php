<?php
namespace Fiserv\Payments\Model\CashApp;

class TokenExtractor
{
	public function extract(array $response): array
	{
		$gateway = $response['gatewayResponse'] ?? [];
		if (($gateway['approvalStatus'] ?? '') !== 'APPROVED'
			|| !in_array($gateway['transactionState'] ?? '', ['AUTHORIZED', 'CAPTURED', 'SETTLED'], true)
			|| strtoupper($response['source']['sourceType'] ?? '') !== 'CASHAPP'
			|| !is_array($response['paymentTokens'] ?? null)) {
			return [];
		}
		$tokens = [];
		foreach ($response['paymentTokens'] as $token) {
			if (!is_array($token) || !is_string($token['tokenSource'] ?? null)
				|| !is_string($token['tokenData'] ?? null) || trim($token['tokenData']) === '') {
				continue;
			}
			$source = strtoupper($token['tokenSource']);
			if (!in_array($source, ['FISERV_SECURE_VAULT', 'CASHAPP'], true)
				|| (isset($token['tokenResponseCode']) && (string) $token['tokenResponseCode'] !== '000')
				|| (isset($token['tokenResponseDescription']) && strtoupper($token['tokenResponseDescription']) !== 'SUCCESS')) {
				continue;
			}
			if (isset($tokens[$source]) && $tokens[$source]['tokenData'] !== $token['tokenData']) {
				return []; // Ambiguous response: do not guess which same-source token is reusable.
			}
			$tokens[$source] = ['tokenSource' => $token['tokenSource'], 'tokenData' => $token['tokenData']];
		}
		return array_values($tokens);
	}
}
