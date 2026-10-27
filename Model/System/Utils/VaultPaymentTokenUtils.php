<?php
namespace Fiserv\Payments\Model\System\Utils;

/**
 * Fiserv Payments M2 Integration Version
 */

use Magento\Framework\Model\ResourceModel\Db\VersionControl\AbstractDb;

class VaultPaymentTokenUtils extends AbstractDb
{
	/** Cash App supplies no expiry; legacy TIMESTAMP writes may have stored a zero date instead of NULL. */
	public function isCashAppTokenExpired(?string $expiresAt): bool
	{
		if (in_array($expiresAt, [null, '0000-00-00 00:00:00', '0000-00-00'], true)) {
			return false;
		}
		return $expiresAt <= gmdate('Y-m-d H:i:s');
	}

	/** Include inactive/hidden tokens: neither consent nor merchant changes may replace the first token. */
	public function getFirstCashAppToken(int $customerId, bool $forUpdate = false): ?array
	{
		if ($customerId <= 0) {
			return null;
		}
		$select = $this->getConnection()->select()->from($this->getMainTable())
			->where('customer_id = ?', $customerId)
			->where('payment_method_code = ?', 'fiserv_cashapp')
			->order('entity_id ASC')->limit(1);
		if ($forUpdate) {
			$select->forUpdate(true);
		}
		return $this->getConnection()->fetchRow($select) ?: null;
	}


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
            ->where('gateway_token like ?', $token . "%")
            ->where('payment_method_code = ?', $paymentMethodCode)
            ->where('is_active = ?', "1")
            ->where('customer_id = ?', $customerId);
        return $connection->fetchAll($select);
    }


	public function doesTokenExist($tokenData, $paymentMethodCode, $customerId, $expMonth, $expYear)
	{
		$matchingTokenData = $this->getByGatewayToken($tokenData, $paymentMethodCode, $customerId);

		if (count($matchingTokenData) < 1)
		{
			return false;
		}

		$match = false;
		for ($i = 0; $i < count($matchingTokenData); $i++) {
			$exists = json_decode($matchingTokenData[$i]["details"], true)["expirationDate"] ===  ($expMonth . "/" . $expYear);
			if ($exists)
			{
				return $matchingTokenData[$i];
			}
		}

		return $match;
	}
}
