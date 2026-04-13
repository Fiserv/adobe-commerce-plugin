<?php
declare(strict_types=1);

namespace Fiserv\Payments\Service;

/**
 * Parses expiry month/year and maskedCC from vault token detail JSON.
 *
 * Centralises logic that was previously duplicated across:
 *   - PaymentProcessor::extractExpiryFromDetails / populateExpiryFromVaultOnly
 *   - SubscriptionProcessor::tryAttachVaultTokenAndExpiry
 *   - SubscriptionPaymentMethodUpdater::tryUpdateExpiryFromVault
 */
class VaultTokenDetailsParser
{
	/**
	 * Decode raw token details into a PHP array.
	 * Returns null if the input is empty or unparseable.
	 *
	 * @param mixed $raw String JSON or already-decoded array.
	 */
	public function decode($raw): ?array
	{
		if (empty($raw)) {
			return null;
		}
		if (is_array($raw)) {
			return $raw;
		}
		if (is_string($raw)) {
			$decoded = @json_decode($raw, true);
			return is_array($decoded) ? $decoded : null;
		}
		return null;
	}

	/**
	 * Extract [expiryMonth, expiryYear] from vault token details.
	 *
	 * Searches multiple candidate sub-objects (card, source.card, paymentTokens[0])
	 * and handles both combined "MM/YYYY" strings and separate month/year keys.
	 *
	 * @param mixed $tokenDetails Raw JSON string or decoded array.
	 * @return array{0: string|null, 1: string|null} [expiryMonth, expiryYear] — both null if not found.
	 */
	public function extractExpiry($tokenDetails): array
	{
		$decoded = $this->decode($tokenDetails);
		if ($decoded === null) {
			return [null, null];
		}

		$candidates = $this->buildCandidates($decoded);

		$expiryMonth = null;
		$expiryYear  = null;

		foreach ($candidates as $candidate) {
			if (!is_array($candidate)) {
				continue;
			}

			// Combined "MM/YYYY" or "MM-YYYY" field (VaultDetailsHandler default)
			if (!$expiryMonth || !$expiryYear) {
				$combined = $candidate['expirationDate'] ?? $candidate['expiration_date'] ?? null;
				if (is_string($combined) && $combined !== '') {
					$parts = preg_split('/[\/\-]/', $combined);
					if (count($parts) >= 2) {
						$expiryMonth = $expiryMonth ?: $this->normalizeMonth($parts[0]);
						$expiryYear  = $expiryYear  ?: $this->normalizeYear($parts[1]);
					}
				}
			}

			// Separate month keys
			if (!$expiryMonth) {
				$expiryMonth = $this->normalizeMonth(
					$candidate['expirationMonth'] ?? $candidate['expMonth'] ?? $candidate['expiration_month'] ?? $candidate['exp_month'] ?? null
				);
			}

			// Separate year keys
			if (!$expiryYear) {
				$expiryYear = $this->normalizeYear(
					$candidate['expirationYear'] ?? $candidate['expYear'] ?? $candidate['expiration_year'] ?? $candidate['exp_year'] ?? null
				);
			}

			if ($expiryMonth && $expiryYear) {
				break;
			}
		}

		return [$expiryMonth ?: null, $expiryYear ?: null];
	}

	/**
	 * Extract the maskedCC value (e.g. "************1111") from vault token details.
	 *
	 * @param mixed $tokenDetails Raw JSON string or decoded array.
	 */
	public function extractMaskedCC($tokenDetails): string
	{
		$decoded = $this->decode($tokenDetails);
		if ($decoded === null) {
			return '';
		}
		return (string)($decoded['maskedCC'] ?? '');
	}

	/**
	 * Extract the tokenSource value from vault token details.
	 *
	 * @param mixed $tokenDetails Raw JSON string or decoded array.
	 */
	public function extractTokenSource($tokenDetails): ?string
	{
		$decoded = $this->decode($tokenDetails);
		if ($decoded === null) {
			return null;
		}

		foreach ($this->buildCandidates($decoded) as $candidate) {
			if (!is_array($candidate)) {
				continue;
			}
			$source = $candidate['tokenSource'] ?? $candidate['token_source'] ?? $candidate['tokenResponseDescription'] ?? null;
			if ($source !== null) {
				return (string)$source;
			}
		}
		return null;
	}

	// -------------------------------------------------------------------------
	// Normalisation helpers (public so PaymentProcessor can reuse them directly)
	// -------------------------------------------------------------------------

	public function normalizeMonth($month): ?string
	{
		if ($month === null) {
			return null;
		}
		$month = trim((string)$month);
		if (preg_match('/^\d{1,2}$/', $month)) {
			$int = (int)$month;
			if ($int >= 1 && $int <= 12) {
				return str_pad((string)$int, 2, '0', STR_PAD_LEFT);
			}
		}
		return null;
	}

	public function normalizeYear($year): ?string
	{
		if ($year === null) {
			return null;
		}
		$year = trim((string)$year);
		if (preg_match('/^\d{4}$/', $year)) {
			return $year;
		}
		if (preg_match('/^\d{2}$/', $year)) {
			return '20' . $year;
		}
		return null;
	}
	
	/** Build ordered list of candidate sub-objects to search for expiry/source data. */
	private function buildCandidates(array $decoded): array
	{
		$candidates = [$decoded];

		if (!empty($decoded['card']) && is_array($decoded['card'])) {
			$candidates[] = $decoded['card'];
		}
		if (!empty($decoded['source']) && is_array($decoded['source'])) {
			$candidates[] = $decoded['source'];
			if (!empty($decoded['source']['card']) && is_array($decoded['source']['card'])) {
				$candidates[] = $decoded['source']['card'];
			}
		}
		if (!empty($decoded['paymentTokens']) && is_array($decoded['paymentTokens'])) {
			foreach ($decoded['paymentTokens'] as $entry) {
				if (is_array($entry)) {
					$candidates[] = $entry;
				}
			}
		}

		return $candidates;
	}
}

