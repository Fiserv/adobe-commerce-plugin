<?php
namespace Fiserv\Payments\Model\CashApp;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\DuplicateException;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Serialize\Serializer\Json;

class TokenStorage
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

	public function hasForCustomer(int $customerId): bool
	{
		if ($customerId <= 0) {
			return false;
		}
		$connection = $this->resource->getConnection();
		return (bool) $connection->fetchOne($connection->select()
			->from($this->resource->getTableName('fiserv_cashapp_token'), ['entity_id'])
			->where('customer_id = ?', $customerId)->limit(1));
	}

	/** No update, merge or upsert: the first successful insert wins globally for this customer. */
	public function saveFirst(int $customerId, array $scope, array $tokens): bool
	{
		if ($customerId <= 0 || !$tokens || $this->hasForCustomer($customerId)) {
			return false;
		}
		foreach (['website_id', 'merchant_id', 'terminal_id', 'environment'] as $key) {
			if (empty($scope[$key])) {
				throw new \InvalidArgumentException('Incomplete Cash App merchant scope.');
			}
		}
		try {
			$this->resource->getConnection()->insert($this->resource->getTableName('fiserv_cashapp_token'), [
				'customer_id' => $customerId,
				'website_id' => (int) $scope['website_id'],
				'merchant_id' => (string) $scope['merchant_id'],
				'terminal_id' => (string) $scope['terminal_id'],
				'environment' => (string) $scope['environment'],
				'token_source' => self::preferredToken($tokens)['tokenSource'] ?? null,
				'token_data' => $this->encryptor->encrypt($this->json->serialize(array_values($tokens))),
			]);
		} catch (DuplicateException $exception) {
			// Another checkout saved first. Never overwrite its collection.
			return false;
		}
		return true;
	}

	/** Never return this result to checkout configuration, controllers or Admin displays. */
	public function getReusableToken(int $customerId, array $scope): ?array
	{
		if ($customerId <= 0) {
			return null;
		}
		$connection = $this->resource->getConnection();
		$query = $connection->select()->from($this->resource->getTableName('fiserv_cashapp_token'), ['token_data'])
			->where('customer_id = ?', $customerId)->limit(1);
		foreach (['website_id', 'merchant_id', 'terminal_id', 'environment'] as $key) {
			if (empty($scope[$key])) {
				return null;
			}
			$query->where($key . ' = ?', $scope[$key]);
		}
		$encrypted = $connection->fetchOne($query);
		if (!$encrypted) {
			return null;
		}
		try {
			$tokens = $this->json->unserialize($this->encryptor->decrypt($encrypted));
			$token = is_array($tokens) ? self::preferredToken($tokens) : null;
			// Only the demonstrated secure-vault PaymentToken contract is enabled for reuse.
			return ($token['tokenSource'] ?? '') === 'FISERV_SECURE_VAULT' ? $token : null;
		} catch (\Throwable $exception) {
			return null;
		}
	}

	/** Deterministic selection preserves each source/data pair; ciphertext remains authoritative. */
	public static function preferredToken(array $tokens): ?array
	{
		$pairs = [];
		foreach ($tokens as $token) {
			if (!is_array($token) || !is_string($token['tokenSource'] ?? null)
				|| !is_string($token['tokenData'] ?? null) || trim($token['tokenData']) === '') {
				continue;
			}
			$source = strtoupper($token['tokenSource']);
			if (!in_array($source, ['FISERV_SECURE_VAULT', 'CASHAPP'], true)) {
				continue;
			}
			if (isset($pairs[$source]) && $pairs[$source]['tokenData'] !== $token['tokenData']) {
				return null;
			}
			$pairs[$source] = ['tokenSource' => $source, 'tokenData' => $token['tokenData']];
		}
		return $pairs['FISERV_SECURE_VAULT'] ?? $pairs['CASHAPP'] ?? null;
	}
}
