<?php
namespace Fiserv\Payments\Controller\Valuelink;

use Fiserv\Payments\Model\Adapter\Valuelink\ValuelinkCredentialsRequest;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Webapi\Exception;
use Fiserv\Payments\Logger\MultiLevelLogger;

class GetValuelinkCredentials extends Action implements HttpPostActionInterface
{
	/** @var ValuelinkCredentialsRequest */
	private $chAdapter;

	/** @var MultiLevelLogger */
	private $logger;

	public function __construct(
		Context $context,
		ValuelinkCredentialsRequest $chAdapter,
		MultiLevelLogger $logger
	) {
		parent::__construct($context);
		$this->chAdapter = $chAdapter;
		$this->logger = $logger;
	}

	public function execute()
	{
		$response = $this->resultFactory->create(ResultFactory::TYPE_JSON);

		try {
			$response->setData(['ch_credentials' => $this->chAdapter->requestCredentials()]);
		} catch (\Exception $e) {
			$this->logger->logCritical(1, "An error occurred retrieving Valuelink credentials");
			$this->logger->logCritical(2, $e);
			return $this->processBadRequest($response);
		}

		return $response;
	}

	private function processBadRequest(ResultInterface $response): ResultInterface
	{
		$response->setHttpResponseCode(Exception::HTTP_BAD_REQUEST);
		$response->setData(['message' => __('Sorry, but something went wrong')]);
		return $response;
	}
}