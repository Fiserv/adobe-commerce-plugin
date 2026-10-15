<?php
namespace Fiserv\Payments\Setup\Patch\Data;

use Fiserv\Payments\Model\CashApp\TokenStorage;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class BackfillCashAppTokenSource implements DataPatchInterface
{
	private $resource;
	private $encryptor;
	private $json;

	public function __construct(ResourceConnection $resource, EncryptorInterface $encryptor, Json $json)
	{
		$this->resource = $resource;
		$this->encryptor = $encryptor;
		$this->json = $json;
	}

	public function apply()
	{
		$connection = $this->resource->getConnection();
		$table = $this->resource->getTableName('fiserv_cashapp_token');
		$lastId = 0;
		do {
			$rows = $connection->fetchAll($connection->select()->from($table, ['entity_id', 'token_data'])
				->where('entity_id > ?', $lastId)->where('token_source IS NULL')->order('entity_id ASC')->limit(500));
			foreach ($rows as $row) {
				$lastId = (int) $row['entity_id'];
				try {
					$tokens = $this->json->unserialize($this->encryptor->decrypt($row['token_data']));
					$token = is_array($tokens) ? TokenStorage::preferredToken($tokens) : null;
				} catch (\Throwable $exception) {
					// Never log ciphertext, decrypted values or exception details containing token data.
					continue;
				}
				if ($token !== null) {
					$connection->update($table, ['token_source' => $token['tokenSource']], [
						'entity_id = ?' => $lastId,
						'token_source IS NULL',
					]);
				}
			}
		} while (count($rows) === 500);
		return $this;
	}

	public static function getDependencies()
	{
		return [];
	}

	public function getAliases()
	{
		return [];
	}
}
