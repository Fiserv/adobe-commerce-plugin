<?php
namespace Fiserv\Payments\Model\AffirmVirtualCard;

use Fiserv\Payments\Gateway\Config\AffirmVirtualCard\Config;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;

/** Stores only hashes/references and payment context, never sdkData or card credentials. */
class SessionRegistry
{
	private const TABLE = 'fiserv_affirm_virtual_card_session';
	private ResourceConnection $resource;
	private Config $config;

	public function __construct(ResourceConnection $resource, Config $config)
	{
		$this->resource = $resource;
		$this->config = $config;
	}

	public function register(string $sessionId, int $quoteId, int $storeId, float $amount, string $currency): void
	{
		$connection = $this->resource->getConnection();
		$table = $this->resource->getTableName(self::TABLE);
		$connection->beginTransaction();
		try {
			$row = [
				'session_hash' => hash('sha256', $sessionId),
				'quote_id' => $quoteId,
				'store_id' => $storeId,
				'amount' => round($amount, 2),
				'currency' => $currency,
				'payment_action' => $this->config->getPaymentAction($storeId),
				'status' => 'ready',
				'expires_at' => gmdate('Y-m-d H:i:s', time() + 600),
			];
			$updated = $connection->update($table, $row, [
				'quote_id = ?' => $quoteId,
				'status IN (?)' => ['ready', 'invalid', 'failed'],
			]);
			if ($updated !== 1) {
				// The unique quote key prevents replacing a consumed session, including under concurrency.
				$connection->insert($table, $row);
			}
			$connection->commit();
		} catch (\Throwable $exception) {
			$connection->rollBack();
			throw $exception;
		}
	}

	public function invalidate(int $quoteId, ?string $sessionId = null): void
	{
		$where = ['quote_id = ?' => $quoteId, 'status = ?' => 'ready'];
		if ($sessionId !== null) {
			$where['session_hash = ?'] = hash('sha256', $sessionId);
		}
		$this->resource->getConnection()->update(
			$this->resource->getTableName(self::TABLE), ['status' => 'invalid'], $where
		);
	}

	/** Atomic compare-and-set: arbitrary, expired, replaced and reused references are rejected. */
	public function consume(string $sessionId, int $quoteId, int $storeId, float $amount, string $currency): void
	{
		if (!$this->config->isActive($storeId) || $sessionId === '' || $quoteId <= 0) {
			throw new LocalizedException(__('Affirm Virtual Card is unavailable.'));
		}
		$changed = $this->resource->getConnection()->update(
			$this->resource->getTableName(self::TABLE),
			['status' => 'consumed'],
			[
				'session_hash = ?' => hash('sha256', $sessionId),
				'quote_id = ?' => $quoteId,
				'store_id = ?' => $storeId,
				'amount = ?' => round($amount, 2),
				'currency = ?' => $currency,
				'payment_action = ?' => $this->config->getPaymentAction($storeId),
				'status = ?' => 'ready',
				'expires_at > ?' => gmdate('Y-m-d H:i:s'),
			]
		);
		if ($changed !== 1) {
			throw new LocalizedException(__('This Affirm Virtual Card session is no longer valid. Please retry checkout.'));
		}
	}

	public function isVirtualCardRequest(array $request): bool
	{
		$connection = $this->resource->getConnection();
		$sessionId = $request['request']['session']['sessionId'] ?? null;
		if (is_string($sessionId) && $sessionId !== '') {
			return (bool) $connection->fetchOne($connection->select()
				->from($this->resource->getTableName(self::TABLE), ['session_hash'])
				->where('session_hash = ?', hash('sha256', $sessionId)));
		}
		$incrementId = $request['orderIncrementId'] ?? '';
		if ($incrementId === '') {
			return false;
		}
		$method = $connection->fetchOne($connection->select()
			->from(['o' => $this->resource->getTableName('sales_order')], [])
			->join(['p' => $this->resource->getTableName('sales_order_payment')], 'p.parent_id = o.entity_id', ['method'])
			->where('o.increment_id = ?', $incrementId));
		return $method === Config::CODE;
	}

	public function assertCanCreate(int $quoteId): void
	{
		$connection = $this->resource->getConnection();
		$status = $connection->fetchOne($connection->select()
			->from($this->resource->getTableName(self::TABLE), ['status'])
			->where('quote_id = ?', $quoteId));
		if ($status === 'consumed') {
			throw new LocalizedException(__('An Affirm Virtual Card attempt requires payment-status reconciliation before retry.'));
		}
	}

	public function recordOutcome(array $request, array $response): void
	{
		$sessionId = $request['request']['session']['sessionId'] ?? null;
		// A timeout/202/technical error is not proof that no payment occurred.
		if (!is_string($sessionId) || ($response['gatewayResponse']['transactionState'] ?? null) !== 'DECLINED') {
			return;
		}
		$this->resource->getConnection()->update($this->resource->getTableName(self::TABLE), ['status' => 'failed'], [
			'session_hash = ?' => hash('sha256', $sessionId),
			'status = ?' => 'consumed',
		]);
	}
}
