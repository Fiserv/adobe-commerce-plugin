<?php
namespace Fiserv\Payments\Model\PayByLink;

use Magento\Framework\Exception\InputException;
use Magento\Payment\Gateway\Http\ClientException;

class PaymentLinkErrorResolver
{
    /**
     * @param \Throwable $exception
     * @return array
     */
    public function resolve(\Throwable $exception)
    {
        if ($exception instanceof InputException) {
            return [
                'http_status' => 0,
                'failure_type' => 'invalid_input',
                'error_code' => 'PBL_INVALID_INPUT',
                'user_message' => $exception->getMessage(),
                'guidance' => $this->getGuidance('invalid_input'),
                'technical_message' => $this->trimMessage($exception->getMessage()),
            ];
        }

        $httpStatus = $this->extractHttpStatus($exception);
        $failureType = $this->extractFailureType($exception, $httpStatus);
        $errorCode = $this->extractErrorCode($exception, $httpStatus);

        return [
            'http_status' => $httpStatus,
            'failure_type' => $failureType,
            'error_code' => $errorCode,
            'user_message' => $this->getUserMessage($failureType),
            'guidance' => $this->getGuidance($failureType),
            'technical_message' => $this->trimMessage($exception->getMessage()),
        ];
    }

    /**
     * @param string $failureType
     * @return string
     */
    private function getUserMessage($failureType)
    {
        switch ($failureType) {
            case 'network':
                return 'We could not reach the payment service while generating your payment link.';
            case 'invalid_input':
                return 'Some payment link details are invalid or incomplete.';
            case 'authentication':
                return 'We could not authenticate with the payment service.';
            case 'server_error':
                return 'The payment service is temporarily unavailable.';
            default:
                return 'We are currently unable to generate your payment link.';
        }
    }

    /**
     * @param string $failureType
     * @return string
     */
    private function getGuidance($failureType)
    {
        switch ($failureType) {
            case 'network':
                return 'Please retry in a moment. If this continues, check network connectivity and contact support.';
            case 'invalid_input':
                return 'Please review order details and required fields, then retry the action.';
            case 'authentication':
                return 'Please verify API credentials and merchant configuration, then retry.';
            case 'server_error':
                return 'Please retry shortly. If the issue persists, contact support with the order number.';
            default:
                return 'Please retry the action. If the issue persists, contact support.';
        }
    }

    /**
     * @param \Throwable $exception
     * @return int
     */
    private function extractHttpStatus(\Throwable $exception)
    {
        if ($exception instanceof PaymentLinkSessionException) {
            return (int) $exception->getHttpStatus();
        }

        if (preg_match('/HTTP\s*code\s*:\s*(\d{3})/i', (string) $exception->getMessage(), $matches)) {
            return (int) $matches[1];
        }

        $code = (int) $exception->getCode();
        if ($code >= 100 && $code <= 599) {
            return $code;
        }

        return 0;
    }

    /**
     * @param \Throwable $exception
     * @param int $httpStatus
     * @return string
     */
    private function extractFailureType(\Throwable $exception, $httpStatus)
    {
        if ($exception instanceof PaymentLinkSessionException && $exception->getFailureType()) {
            return (string) $exception->getFailureType();
        }

        if ($httpStatus === 401 || $httpStatus === 403) {
            return 'authentication';
        }
        if ($httpStatus >= 400 && $httpStatus < 500) {
            return 'invalid_input';
        }
        if ($httpStatus >= 500) {
            return 'server_error';
        }

        $message = strtolower((string) $exception->getMessage());
        if ($exception instanceof ClientException
            || strpos($message, 'timeout') !== false
            || strpos($message, 'timed out') !== false
            || strpos($message, 'curl error') !== false
            || strpos($message, 'could not resolve host') !== false
            || strpos($message, 'connection') !== false
        ) {
            return 'network';
        }
        if (strpos($message, 'unauthorized') !== false
            || strpos($message, 'forbidden') !== false
            || strpos($message, 'authentication') !== false
            || strpos($message, 'credential') !== false
        ) {
            return 'authentication';
        }
        if (strpos($message, 'invalid') !== false
            || strpos($message, 'missing') !== false
            || strpos($message, 'required') !== false
            || strpos($message, 'format') !== false
        ) {
            return 'invalid_input';
        }

        return 'unknown';
    }

    /**
     * @param \Throwable $exception
     * @param int $httpStatus
     * @return string
     */
    private function extractErrorCode(\Throwable $exception, $httpStatus)
    {
        if ($exception instanceof PaymentLinkSessionException && $exception->getErrorCode()) {
            return (string) $exception->getErrorCode();
        }

        if (preg_match('/\bcode\s*[:=]\s*([A-Z0-9_\-]+)\b/i', (string) $exception->getMessage(), $matches)) {
            return strtoupper((string) $matches[1]);
        }

        if ($httpStatus > 0) {
            return 'HTTP_' . $httpStatus;
        }

        return 'PBL_UNKNOWN_ERROR';
    }

    /**
     * @param string $message
     * @return string
     */
    private function trimMessage($message)
    {
        $clean = trim((string) $message);
        if ($clean === '') {
            return 'Unknown error';
        }
        if (strlen($clean) > 500) {
            return substr($clean, 0, 500) . '...';
        }
        return $clean;
    }
}