<?php
namespace Fiserv\Payments\Block\Adminhtml\Paybylink;

use Magento\Backend\Block\Template;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json as SerializerJson;
use Magento\Framework\Serialize\Serializer\Serialize as PhpSerializer;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Data\Form\FormKey;
use Fiserv\Payments\Helper\PayByLink\ExpiryTimeConverter;

class Manage extends Template
{
    protected $_template = 'Fiserv_Payments::dashboard/manage_payment_links.phtml';

    private $orderCollectionFactory;
    private $resourceConnection;
    private $pricingHelper;
    private $timezone;
    private $scopeConfig;
    private $serializer;
    private $phpSerializer;
    protected $formKey;

    public function __construct(
        Template\Context $context,
        OrderCollectionFactory $orderCollectionFactory,
        ResourceConnection $resourceConnection,
        PricingHelper $pricingHelper,
        TimezoneInterface $timezone,
        ScopeConfigInterface $scopeConfig,
        SerializerJson $serializer,
        PhpSerializer $phpSerializer,
        FormKey $formKey,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->resourceConnection = $resourceConnection;
        $this->pricingHelper = $pricingHelper;
        $this->timezone = $timezone;
        $this->scopeConfig = $scopeConfig;
        $this->serializer = $serializer;
        $this->phpSerializer = $phpSerializer;
        $this->formKey = $formKey;
    }

    /**
     * Get the current form key for POST actions.
     */
    public function getFormKey()
    {
        return $this->formKey->getFormKey();
    }

    /**
     * Get filter parameters from request
     */
    public function getFilterParams()
    {
        return [
            'order_number' => trim((string)$this->getRequest()->getParam('order_number', '')),
            'amount_from' => trim((string)$this->getRequest()->getParam('amount_from', '')),
            'amount_to' => trim((string)$this->getRequest()->getParam('amount_to', '')),
            'created_from' => trim((string)$this->getRequest()->getParam('created_from', '')),
            'created_to' => trim((string)$this->getRequest()->getParam('created_to', '')),
            'expires_from' => trim((string)$this->getRequest()->getParam('expires_from', '')),
            'expires_to' => trim((string)$this->getRequest()->getParam('expires_to', '')),
            'status' => trim((string)$this->getRequest()->getParam('status', '')),
            'search' => trim((string)$this->getRequest()->getParam('search', '')),
        ];
    }

    public function getRows()
    {
        $rows = $this->getData('rows');
        if (!empty($rows)) {
            return $rows;
        }
        
        $filters = $this->getFilterParams();
        $connection = $this->resourceConnection->getConnection();
        $orderTable = $connection->getTableName('sales_order');
        $paymentTable = $connection->getTableName('sales_order_payment');

        $select = $connection->select()
            ->from(['so' => $orderTable], ['entity_id', 'increment_id', 'grand_total', 'created_at', 'status'])
            ->join(['p' => $paymentTable], 'so.entity_id = p.parent_id', ['additional_information'])
            ->where('p.method = ?', 'fiserv_pay_by_link');

        // Order number filter (text search)
        if ($filters['order_number'] !== '') {
            $like = '%' . $filters['order_number'] . '%';
            $select->where('so.increment_id LIKE ?', $like);
        }

        // Amount range filters
        if ($filters['amount_from'] !== '') {
            $select->where('so.grand_total >= ?', (float)$filters['amount_from']);
        }
        if ($filters['amount_to'] !== '') {
            $select->where('so.grand_total <= ?', (float)$filters['amount_to']);
        }

        // Created date range filters
        if ($filters['created_from'] !== '') {
            $createdFrom = date('Y-m-d H:i:s', strtotime($filters['created_from']));
            $select->where('so.created_at >= ?', $createdFrom);
        }
        if ($filters['created_to'] !== '') {
            $createdTo = date('Y-m-d H:i:s', strtotime($filters['created_to'] . ' 23:59:59'));
            $select->where('so.created_at <= ?', $createdTo);
        }

        // Expiration date range filters (calculated from created_at + expiry time)
        if ($filters['expires_from'] !== '' || $filters['expires_to'] !== '') {
            $raw = $this->scopeConfig->getValue('payment/fiserv_pay_by_link/expiry_time', ScopeInterface::SCOPE_STORE);
            $expiryCfg = $raw ? $this->serializer->unserialize($raw) : [];
            $totalSeconds = ExpiryTimeConverter::configToSeconds($expiryCfg);
            
            if ($totalSeconds > 0) {
                // For expiration filter, we need to check the calculated expiration date
                // Since expiration is calculated, we'll filter by created_at + expiry time
                if ($filters['expires_from'] !== '') {
                    $expiresFrom = date('Y-m-d H:i:s', strtotime($filters['expires_from']));
                    // We need to find orders where created_at + expiry >= expires_from
                    // This is equivalent to created_at >= expires_from - expiry
                    $createdFromForExpires = strtotime($expiresFrom) - $totalSeconds;
                    $select->where('so.created_at >= ?', date('Y-m-d H:i:s', $createdFromForExpires));
                }
                if ($filters['expires_to'] !== '') {
                    $expiresTo = date('Y-m-d H:i:s', strtotime($filters['expires_to'] . ' 23:59:59'));
                    // We need to find orders where created_at + expiry <= expires_to
                    // This is equivalent to created_at <= expires_to - expiry
                    $createdToForExpires = strtotime($expiresTo) - $totalSeconds;
                    $select->where('so.created_at <= ?', date('Y-m-d H:i:s', $createdToForExpires));
                }
            }
        }

        // Legacy search filter (for backward compatibility)
        if ($filters['search'] !== '') {
            $like = '%' . $filters['search'] . '%';
            $select->where('so.increment_id LIKE ? OR so.grand_total LIKE ? OR so.status LIKE ?', $like);
        }

        // Status filter (dropdown)
        if ($filters['status'] !== '' && strtolower($filters['status']) !== 'all') {
            $select->where('so.status = ?', $filters['status']);
        }

        $select->order('so.created_at DESC');

        $results = $connection->fetchAll($select);

        $now = time();
        $rows = [];
        foreach ($results as $order) {
            $viewUrl = $this->getUrl('sales/order/view', ['order_id' => $order['entity_id']]);
            $retryUrl = $this->getUrl('fiserv/paybylink/retry', ['order_id' => $order['entity_id']]);
            $actionHtml = '';

            $amountFormatted = $this->pricingHelper->currency($order['grand_total'], true, false);
            $createdFormatted = $this->timezone->formatDateTime($order['created_at'], \IntlDateFormatter::MEDIUM, \IntlDateFormatter::SHORT);
            $createdTs = strtotime($order['created_at']);

            $payByLinkUrl = '';
            if (!empty($order['additional_information'])) {
                $rawInfo = $order['additional_information'];
                $decoded = json_decode($rawInfo, true);
                if (is_array($decoded)) {
                    $flat = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($decoded));
                    foreach ($flat as $v) {
                        if (is_string($v) && preg_match('#^https?://#i', $v)) { $payByLinkUrl = $v; break; }
                    }
                } else {
                    $maybe = $this->unserializeLegacyData($rawInfo);
                    if (is_array($maybe)) {
                        $flat = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($maybe));
                        foreach ($flat as $v) {
                            if (is_string($v) && preg_match('#^https?://#i', $v)) { $payByLinkUrl = $v; break; }
                        }
                    }
                }
            }
            
            if (empty($payByLinkUrl)) {
                $baseUrl = $this->_storeManager->getStore()->getBaseUrl();
                $payByLinkUrl = ''; // No valid fallback URL available - original PBL session creation must have failed
            }

            $expiresFormatted = '';
            $expiresTs = null;
            $expiresRaw = '';
            $pblCreatedAt = '';
            try {
                // Try to get pbl_created_at and pbl_expires_in_minutes from additional_information
                $pblExpiresInMinutes = 0;
                if (!empty($order['additional_information'])) {
                    $rawInfo = $order['additional_information'];
                    $decoded = json_decode($rawInfo, true);
                    if (!is_array($decoded)) {
                        $decoded = $this->unserializeLegacyData($rawInfo);
                    }
                    if (is_array($decoded)) {
                        $pblCreatedAt = $decoded['pbl_created_at'] ?? '';
                        $pblExpiresInMinutes = (int) ($decoded['pbl_expires_in_minutes'] ?? 0);
                    }
                }

                if ($pblCreatedAt && $pblExpiresInMinutes > 0) {
                    // Use PBL-specific creation time + expiry minutes
                    $pblCreatedTs = strtotime($pblCreatedAt);
                    if ($pblCreatedTs !== false) {
                        $expiresTs = $pblCreatedTs + ($pblExpiresInMinutes * 60);
                        $expiresRaw = date('Y-m-d H:i:s', $expiresTs);
                        $expiresFormatted = $this->timezone->formatDateTime($expiresRaw, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::SHORT);
                    }
                } else {
                    // Fallback to config-based expiry from order creation
                    $raw = $this->scopeConfig->getValue('payment/fiserv_pay_by_link/expiry_time', ScopeInterface::SCOPE_STORE);
                    $expiryCfg = $raw ? $this->serializer->unserialize($raw) : [];
                    $totalSeconds = ExpiryTimeConverter::configToSeconds($expiryCfg);
                    if ($totalSeconds > 0 && $createdTs !== false) {
                        $expiresTs = $createdTs + $totalSeconds;
                        $expiresRaw = date('Y-m-d H:i:s', $expiresTs);
                        $expiresFormatted = $this->timezone->formatDateTime($expiresRaw, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::SHORT);
                    }
                }
            } catch (\Exception $e) {
                $expiresFormatted = '';
            }

            // Use pbl_created_at for display if available (reflects regeneration)
            $displayCreated = $order['created_at'];
            $displayCreatedTs = $createdTs;
            if ($pblCreatedAt) {
                $pblTs = strtotime($pblCreatedAt);
                if ($pblTs !== false) {
                    $displayCreated = $pblCreatedAt;
                    $displayCreatedTs = $pblTs;
                }
            }
            $displayCreatedFormatted = $this->timezone->formatDateTime($displayCreated, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::SHORT);

            $row = [
                'order' => $order['increment_id'],
                'amount' => $order['grand_total'],
                'amount_formatted' => $amountFormatted,
                'created' => $displayCreated,
                'created_formatted' => $displayCreatedFormatted,
                'created_ts' => $displayCreatedTs !== false ? (int)$displayCreatedTs : null,
                'expires' => $expiresFormatted,
                'expires_raw' => $expiresRaw,
                'expires_ts' => isset($expiresTs) && $expiresTs ? (int)$expiresTs : null,
                'status' => strtoupper($order['status']),
                'status_code' => strtolower((string)$order['status']),
                'action' => $actionHtml,
                'retry_url' => $retryUrl,
                'paybylink_url' => $payByLinkUrl,
                'entity_id' => $order['entity_id'],
                'pbl_status' => $this->extractPblStatusFromRaw($order['additional_information'] ?? '')
            ];

            // Determine final status based on pbl_status field, not order status alone
            $pblStatus = $row['pbl_status'];
            if ($pblStatus === 'paid') {
                $row['status'] = 'PAID';
                $row['status_code'] = 'paid';
            } elseif ($pblStatus === 'unpaid') {
                // Explicitly set by regeneration or initial creation — trust it
                if ($this->isLinkExpired($row, $now)) {
                    $row['status'] = 'EXPIRED';
                    $row['status_code'] = 'expired';
                } else {
                    $row['status'] = 'UNPAID';
                    $row['status_code'] = 'unpaid';
                }
            } elseif (in_array($row['status_code'], ['complete', 'closed'])) {
                // Order completed through other means
                $row['status'] = 'PAID';
                $row['status_code'] = 'paid';
            } elseif ($pblStatus === 'declined' || $pblStatus === 'failed') {
                $row['status'] = 'EXPIRED';
                $row['status_code'] = 'expired';
            } elseif ($this->isLinkExpired($row, $now)) {
                $row['status'] = 'EXPIRED';
                $row['status_code'] = 'expired';
            } else {
                $row['status'] = 'UNPAID';
                $row['status_code'] = 'unpaid';
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Extract only the pbl_status value from the raw additional_information payload.
     *
     * Avoids exposing the full additional_information blob (which may contain
     * payment tokens, card metadata, and other sensitive data) to the view.
     */
    private function extractPblStatusFromRaw($rawInfo)
    {
        if (empty($rawInfo) || !is_string($rawInfo)) {
            return '';
        }
        $decoded = json_decode($rawInfo, true);
        if (is_array($decoded) && isset($decoded['pbl_status'])) {
            return strtolower($decoded['pbl_status']);
        }
        $maybe = $this->unserializeLegacyData($rawInfo);
        if (is_array($maybe) && isset($maybe['pbl_status'])) {
            return strtolower($maybe['pbl_status']);
        }
        return '';
    }

    /**
     * Decode legacy PHP-serialized payment data.
     */
    private function unserializeLegacyData($rawInfo)
    {
        try {
            return $this->phpSerializer->unserialize($rawInfo);
        } catch (\InvalidArgumentException $exception) {
            return [];
        }
    }

    /**
     * Check if link is expired based on expiration timestamp
     */
    private function isLinkExpired(array $row, $now)
    {
        return isset($row['expires_ts']) && $row['expires_ts'] && $now > $row['expires_ts'];
    }
}
