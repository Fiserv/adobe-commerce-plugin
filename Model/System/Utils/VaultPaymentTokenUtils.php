<?php
namespace Fiserv\Payments\Model\System\Utils;

use Magento\Framework\Model\ResourceModel\Db\VersionControl\AbstractDb;

class VaultPaymentTokenUtils extends AbstractDb
{
	protected function _construct()
	{
		$this->_init('vault_payment_token', 'entity_id');
	}

	public function getByGatewayToken($token, $paymentMethodCode, $customerId)
	{
		$connection = $this->getConnection();
		$select = $connection
			->select()
			->from($this->getMainTable())
			->where('gateway_token like ?', $token . '%')
			->where('payment_method_code = ?', $paymentMethodCode)
			->where('is_active = ?', '1')
			->where('customer_id = ?', $customerId);

		return $connection->fetchAll($select);
	}

	public function doesTokenExist($tokenData, $paymentMethodCode, $customerId, $expMonth, $expYear)
	{
		$matchingTokenData = $this->getByGatewayToken($tokenData, $paymentMethodCode, $customerId);
		if (count($matchingTokenData) < 1) {
			return false;
		}

		$normalizedYear = strlen((string)$expYear) === 2 ? '20' . $expYear : (string)$expYear;
		$shortYear = substr($normalizedYear, -2);
		$expectedExpDates = [
			$expMonth . '/' . $shortYear,
			$expMonth . '/' . $normalizedYear,
		];

		foreach ($matchingTokenData as $row) {
			$details = json_decode($row['details'] ?? '{}', true);
			$storedExpDate = is_array($details) && isset($details['expirationDate']) ? $details['expirationDate'] : null;

			if ($storedExpDate === null || !in_array($storedExpDate, $expectedExpDates, true)) {
				continue;
			}

			if ($this->isTokenExpired($row)) {
				continue;
			}

			return $row;
		}

		return false;
	}

	private function isTokenExpired(array $tokenRow): bool
	{
		if (empty($tokenRow['expires_at'])) {
			return false;
		}

		$expiresAt = strtotime($tokenRow['expires_at'] . ' UTC');
		if ($expiresAt === false) {
			return false;
		}

		return $expiresAt <= time();
	}
}
