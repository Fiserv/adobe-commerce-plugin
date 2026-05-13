<?php

declare(strict_types=1);

namespace Fiserv\Payments\Model\Adapter\CommerceHub;

use Fiserv\Payments\Logger\MultiLevelLogger;

class OpenRefundRequest
{
    public const REFUNDS_ENDPOINT = 'payments/v1/refunds';

    /** @var ChHttpAdapter */
    private $httpAdapter;

    /** @var MultiLevelLogger */
    private $logger;

    public function __construct(
        ChHttpAdapter $httpAdapter,
        MultiLevelLogger $logger
    ) {
        $this->httpAdapter = $httpAdapter;
        $this->logger = $logger;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function request(array $payload): array
    {
        $this->logger->logDebug(3, 'Open refund request payload: ' . json_encode($payload));

        $response = $this->httpAdapter->sendRequest($payload, self::REFUNDS_ENDPOINT);
        $body = json_decode($response->getBody(), true);

        if (!is_array($body)) {
            $body = [];
        }

        $body['_http_status'] = $response->getStatusCode();

        return $body;
    }
}

