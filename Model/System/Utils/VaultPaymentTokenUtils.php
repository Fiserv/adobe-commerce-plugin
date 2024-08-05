<?php
namespace Fiserv\Payments\Model\System\Utils;

/**
 * Fiserv Payments M2 Integration Version
 */

use Magento\Vault\Setup\InstallSchema;
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
			$match = json_decode($matchingTokenData[$i]["details"], true)["expirationDate"] ===  ($expMonth . "/" . $expYear);
			if ($match)
			{
				return $match;
			}
		}

		return $match;
	}
}
