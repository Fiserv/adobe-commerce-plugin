<?php
namespace Fiserv\Payments\Controller\Adminhtml\Paybylink;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json as SerializerJson;
use Magento\Framework\Serialize\Serializer\Serialize as PhpSerializer;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;
use Magento\Sales\Model\Order;
use Fiserv\Payments\Helper\PayByLink\ExpiryTimeConverter;

class Index extends Action implements HttpGetActionInterface
{
    private $resultPageFactory;
    private $orderCollectionFactory;
    private $scopeConfig;
    private $serializer;
    private $phpSerializer;
    private $pricingHelper;

    /**
     * PayByLink status constants
     */
    private const STATUS_PAID = 'paid';
    private const STATUS_UNPAID = 'unpaid';
    private const STATUS_EXPIRED = 'expired';

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        OrderCollectionFactory $orderCollectionFactory,
        ScopeConfigInterface $scopeConfig,
        SerializerJson $serializer,
        PhpSerializer $phpSerializer,
        PricingHelper $pricingHelper
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->scopeConfig = $scopeConfig;
        $this->serializer = $serializer;
        $this->phpSerializer = $phpSerializer;
        $this->pricingHelper = $pricingHelper;
    }

    /**
     * Main execute method - orchestrates the PayByLink management page rendering
     */
    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->prepend(__('Manage Payment Links'));

        $collection = $this->initializeCollection();
        $this->applyFiltersToCollection($collection);
        $collection->setOrder('created_at', 'DESC');

        $rows = $this->buildRowsData($collection);
        $this->updateRowStatuses($rows);

        $block = $resultPage->getLayout()->getBlock('fiserv_paybylink_manage');
        if ($block) {
            $block->setData('rows', $rows);
        }

        return $resultPage;
    }

    /**
     * Initialize order collection with payment table join
     */
    private function initializeCollection()
    {
        $collection = $this->orderCollectionFactory->create();
        $collection->getSelect()->join(
            ['payment' => $collection->getTable('sales_order_payment')],
            'main_table.entity_id = payment.parent_id',
            ['method', 'additional_information']
        );
        $collection->addFieldToFilter('payment.method', 'fiserv_pay_by_link');
        return $collection;
    }

    /**
     * Build row data for all orders in collection
     */
    private function buildRowsData($collection)
    {
        $rows = [];
        foreach ($collection as $order) {
            $rows[] = $this->buildRowData($order);
        }
        return $rows;
    }

    /**
     * Build complete row data array for a single order
     */
    private function buildRowData(Order $order)
    {
        $payByLinkUrl = $this->extractPayByLinkUrl($order);
        $createdTs = $order->getCreatedAt() ? strtotime($order->getCreatedAt()) : false;
        $expiresTs = $this->calculateExpiration($createdTs);

        if (empty($payByLinkUrl)) {
            $payByLinkUrl = $this->generatePayByLinkUrl($order);
        }

        return [
            'order' => $order->getIncrementId(),
            'amount' => $order->getGrandTotal(),
            'amount_formatted' => $this->formatPrice($order->getGrandTotal()),
            'created' => $order->getCreatedAt(),
            'created_ts' => $createdTs !== false ? (int)$createdTs : null,
            'created_formatted' => $createdTs ? date('Y-m-d H:i:s', $createdTs) : $order->getCreatedAt(),
            'expires' => $expiresTs ? date('Y-m-d H:i:s', $expiresTs) : '',
            'expires_raw' => $expiresTs ? date('Y-m-d H:i:s', $expiresTs) : '',
            'expires_ts' => $expiresTs ? (int)$expiresTs : null,
            'status' => $order->getStatus(),
            'status_code' => $order->getStatus(),
            'entity_id' => $order->getId(),
            'paybylink_url' => $payByLinkUrl,
            'order_object' => $order
        ];
    }

    /**
     * Extract PayByLink URL from order's additional_information
     */
    private function extractPayByLinkUrl(Order $order)
    {
        $rawInfo = $order->getData('additional_information');

        if (!$rawInfo && $order->getPayment()) {
            $additionalInfo = $order->getPayment()->getAdditionalInformation();
            if (is_array($additionalInfo)) {
                $rawInfo = json_encode($additionalInfo);
            }
        }

        if (empty($rawInfo) || !is_string($rawInfo)) {
            return '';
        }

        // Try JSON decoding first
        $decoded = json_decode($rawInfo, true);
        if (is_array($decoded)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveArrayIterator($decoded)) as $value) {
                if (is_string($value) && $this->isUrl($value)) {
                    return $value;
                }
            }
        }

        // Try legacy PHP serialization as fallback
        $maybe = $this->unserializeLegacyData($rawInfo);
        if (is_array($maybe)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveArrayIterator($maybe)) as $value) {
                if (is_string($value) && $this->isUrl($value)) {
                    return $value;
                }
            }
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
     * Check if string is a valid URL
     */
    private function isUrl($value)
    {
        return preg_match('#^https?://#i', $value) === 1;
    }

    /**
     * Calculate expiration timestamp from creation date
     */
    private function calculateExpiration($createdTs)
    {
        if ($createdTs === false) {
            return null;
        }

        try {
            $raw = $this->scopeConfig->getValue(
                'payment/fiserv_pay_by_link/expiry_time',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            );
            $expiryConfig = $raw ? $this->serializer->unserialize($raw) : [];

            $totalSeconds = ExpiryTimeConverter::configToSeconds($expiryConfig);

            if ($totalSeconds > 0) {
                return $createdTs + $totalSeconds;
            }
        } catch (\Exception $e) {
            // Log error if needed, then return null
        }

        return null;
    }

    /**
     * Generate fallback PayByLink URL
     */
    private function generatePayByLinkUrl(Order $order)
    {
        $baseUrl = $this->_url->getBaseUrl();
        return ''; // No valid fallback URL available - original PBL session creation must have failed
    }

    /**
     * Update row statuses based on pbl_status and expiration
     */
    private function updateRowStatuses(array &$rows)
    {
        $now = time();

        foreach ($rows as &$row) {
            $row['status_code'] = strtolower($row['status_code'] ?? $row['status'] ?? '');

            // Check pbl_status from payment additional_information
            $pblStatus = $this->extractPblStatusFromOrder($row);

            if ($pblStatus === self::STATUS_PAID) {
                $row['status'] = self::STATUS_PAID;
                $row['status_code'] = self::STATUS_PAID;
            } elseif (in_array($row['status_code'], ['complete', 'closed'])) {
                $row['status'] = self::STATUS_PAID;
                $row['status_code'] = self::STATUS_PAID;
            } elseif ($this->isExpired($row, $now)) {
                $row['status'] = self::STATUS_EXPIRED;
                $row['status_code'] = self::STATUS_EXPIRED;
            } else {
                $row['status'] = self::STATUS_UNPAID;
                $row['status_code'] = self::STATUS_UNPAID;
            }
        }
        unset($row);
    }

    /**
     * Extract pbl_status from order payment additional_information
     */
    private function extractPblStatusFromOrder(array $row)
    {
        if (!isset($row['order_object'])) {
            return '';
        }
        $order = $row['order_object'];
        if ($order->getPayment()) {
            $pblStatus = $order->getPayment()->getAdditionalInformation('pbl_status');
            return $pblStatus ? strtolower($pblStatus) : '';
        }
        return '';
    }

    /**
     * Check if order link is expired
     */
    private function isExpired(array $row, $now)
    {
        return isset($row['expires_ts']) && $row['expires_ts'] && $now > $row['expires_ts'];
    }

    /**
     * Apply filters from request to the collection
     */
    private function applyFiltersToCollection($collection)
    {
        $request = $this->getRequest();

        $this->applyOrderNumberFilter($collection, $request);
        $this->applyAmountRangeFilter($collection, $request);
        $this->applyCreatedDateFilter($collection, $request);
        $this->applyExpirationDateFilter($collection, $request);
        $this->applyStatusFilter($collection, $request);
        $this->applyLegacySearchFilter($collection, $request);
    }

    /**
     * Apply order number filter
     */
    private function applyOrderNumberFilter($collection, $request)
    {
        $orderNumber = trim((string)$request->getParam('order_number', ''));
        if ($orderNumber !== '') {
            $collection->addFieldToFilter('main_table.increment_id', ['like' => '%' . $orderNumber . '%']);
        }
    }

    /**
     * Apply amount range filters
     */
    private function applyAmountRangeFilter($collection, $request)
    {
        $amountFrom = trim((string)$request->getParam('amount_from', ''));
        $amountTo = trim((string)$request->getParam('amount_to', ''));

        if ($amountFrom !== '') {
            $collection->addFieldToFilter('main_table.grand_total', ['gteq' => (float)$amountFrom]);
        }
        if ($amountTo !== '') {
            $collection->addFieldToFilter('main_table.grand_total', ['lteq' => (float)$amountTo]);
        }
    }

    /**
     * Apply created date range filters
     */
    private function applyCreatedDateFilter($collection, $request)
    {
        $createdFrom = trim((string)$request->getParam('created_from', ''));
        $createdTo = trim((string)$request->getParam('created_to', ''));

        if ($createdFrom !== '') {
            $createdFromDate = date('Y-m-d H:i:s', strtotime($createdFrom));
            $collection->addFieldToFilter('main_table.created_at', ['gteq' => $createdFromDate]);
        }
        if ($createdTo !== '') {
            $createdToDate = date('Y-m-d H:i:s', strtotime($createdTo . ' 23:59:59'));
            $collection->addFieldToFilter('main_table.created_at', ['lteq' => $createdToDate]);
        }
    }

    /**
     * Apply expiration date range filters
     */
    private function applyExpirationDateFilter($collection, $request)
    {
        $expiresFrom = trim((string)$request->getParam('expires_from', ''));
        $expiresTo = trim((string)$request->getParam('expires_to', ''));

        if ($expiresFrom === '' && $expiresTo === '') {
            return;
        }

        $totalSeconds = $this->getTotalExpirySeconds();
        if ($totalSeconds <= 0) {
            return;
        }

        if ($expiresFrom !== '') {
            $expiresFromDate = date('Y-m-d H:i:s', strtotime($expiresFrom));
            $createdFromForExpires = strtotime($expiresFromDate) - $totalSeconds;
            $collection->addFieldToFilter(
                'main_table.created_at',
                ['gteq' => date('Y-m-d H:i:s', $createdFromForExpires)]
            );
        }
        if ($expiresTo !== '') {
            $expiresToDate = date('Y-m-d H:i:s', strtotime($expiresTo . ' 23:59:59'));
            $createdToForExpires = strtotime($expiresToDate) - $totalSeconds;
            $collection->addFieldToFilter(
                'main_table.created_at',
                ['lteq' => date('Y-m-d H:i:s', $createdToForExpires)]
            );
        }
    }

    /**
     * Get total expiry seconds from configuration
     */
    private function getTotalExpirySeconds()
    {
        $raw = $this->scopeConfig->getValue(
            'payment/fiserv_pay_by_link/expiry_time',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        $expiryConfig = $raw ? $this->serializer->unserialize($raw) : [];

        return ExpiryTimeConverter::configToSeconds($expiryConfig);
    }

    /**
     * Apply status filter
     */
    private function applyStatusFilter($collection, $request)
    {
        $status = trim((string)$request->getParam('status', ''));
        if ($status !== '' && strtolower($status) !== 'all') {
            $collection->addFieldToFilter('main_table.status', $status);
        }
    }

    /**
     * Apply legacy search filter for backward compatibility
     */
    private function applyLegacySearchFilter($collection, $request)
    {
        $search = trim((string)$request->getParam('search', ''));
        if ($search !== '') {
            $collection->addFieldToFilter([
                ['attribute' => 'main_table.increment_id', ['like' => '%' . $search . '%']],
                ['attribute' => 'main_table.grand_total', ['like' => '%' . $search . '%']],
                ['attribute' => 'main_table.status', ['like' => '%' . $search . '%']]
            ]);
        }
    }

    /**
     * Check if user has permission to access PayByLink management
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Fiserv_Payments::manage_pay_by_link');
    }

    /**
     * Format price with currency
     */
    private function formatPrice($amount)
    {
        return $this->pricingHelper->currency($amount, true, false);
    }
}

