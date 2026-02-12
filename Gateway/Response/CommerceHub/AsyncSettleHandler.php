<?php
/**
 * Async Settle Handler for Affirm
 * Routes to async handling (pending order + inquiry job) when PROCESSING/202,
 * otherwise delegates to standard settle handler
 */
namespace Fiserv\Payments\Gateway\Response\CommerceHub;

use Fiserv\Payments\Gateway\Http\CommerceHub\Client\HttpClient;
use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Magento\Payment\Gateway\Response\HandlerInterface;

class AsyncSettleHandler implements HandlerInterface
{
    const HTTP_ACCEPTED = 202;
    const STATE_PROCESSING = 'PROCESSING';

    /** @var SubjectReader */
    private $subjectReader;

    /** @var HandlerInterface */
    private $defaultHandler;

    /** @var PendingInquiryHandler */
    private $pendingInquiryHandler;

    /** @var AsyncOrderPendingHandler */
    private $asyncOrderPendingHandler;

    /** @var MultiLevelLogger */
    private $logger;

    /**
     * @param SubjectReader $subjectReader
     * @param HandlerInterface $defaultHandler Standard settle handler
     * @param PendingInquiryHandler $pendingInquiryHandler Queues inquiry job
     * @param AsyncOrderPendingHandler $asyncOrderPendingHandler Sets order to pending
     * @param MultiLevelLogger $logger
     */
    public function __construct(
        SubjectReader $subjectReader,
        HandlerInterface $defaultHandler,
        PendingInquiryHandler $pendingInquiryHandler,
        AsyncOrderPendingHandler $asyncOrderPendingHandler,
        MultiLevelLogger $logger
    ) {
        $this->subjectReader = $subjectReader;
        $this->defaultHandler = $defaultHandler;
        $this->pendingInquiryHandler = $pendingInquiryHandler;
        $this->asyncOrderPendingHandler = $asyncOrderPendingHandler;
        $this->logger = $logger;
    }

    /**
     * Handle settle response - route to async or standard handling
     *
     * @param array $handlingSubject
     * @param array $response
     * @return void
     */
    public function handle(array $handlingSubject, array $response): void
    {
        $chResponse = $this->subjectReader->readChResponse($response);
        $statusCode = $chResponse[HttpClient::STATUS_CODE_KEY] ?? 200;
        $responseBody = $chResponse[HttpClient::RESPONSE_KEY] ?? [];
        $transactionState = $responseBody['gatewayResponse']['transactionState'] ?? null;

        $isAsync = ($statusCode == self::HTTP_ACCEPTED) || ($transactionState === self::STATE_PROCESSING);
        
        if ($isAsync) {
            $this->logger->logInfo(1, sprintf(
                'Async capture/settle (Status: %d, State: %s). Marking order pending and queueing inquiry.',
                $statusCode,
                $transactionState ?? 'N/A'
            ));

            // Set order to pending payment state
            $this->asyncOrderPendingHandler->handle($handlingSubject, $response);
            // Queue inquiry job for later processing
            $this->pendingInquiryHandler->handle($handlingSubject, $response);
            return;
        }

        // Standard synchronous settle - use default handler
        $this->defaultHandler->handle($handlingSubject, $response);
    }
}
