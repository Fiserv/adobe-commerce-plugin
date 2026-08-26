<?php
namespace Fiserv\Payments\Model\PayByLink;

class PaymentLinkSessionException extends \RuntimeException
{
    /**
     * @var int
     */
    private $httpStatus;

    /**
     * @var string
     */
    private $errorCode;

    /**
     * @var string
     */
    private $failureType;

    /**
     * @var array
     */
    private $responseBody;

    /**
     * @param string $message
     * @param int $httpStatus
     * @param string $errorCode
     * @param string $failureType
     * @param array $responseBody
     */
    public function __construct(
        $message,
        $httpStatus = 0,
        $errorCode = '',
        $failureType = 'unknown',
        array $responseBody = []
    ) {
        parent::__construct($message);
        $this->httpStatus = (int) $httpStatus;
        $this->errorCode = (string) $errorCode;
        $this->failureType = (string) $failureType;
        $this->responseBody = $responseBody;
    }

    /**
     * @return int
     */
    public function getHttpStatus()
    {
        return $this->httpStatus;
    }

    /**
     * @return string
     */
    public function getErrorCode()
    {
        return $this->errorCode;
    }

    /**
     * @return string
     */
    public function getFailureType()
    {
        return $this->failureType;
    }

    /**
     * @return array
     */
    public function getResponseBody()
    {
        return $this->responseBody;
    }
}