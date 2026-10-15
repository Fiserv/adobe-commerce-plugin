<?php
namespace Fiserv\Payments\Controller\CommerceHub;

use Fiserv\Payments\Gateway\Config\AffirmVirtualCard\Config;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Model\AffirmVirtualCard\CreateSession;
use Fiserv\Payments\Model\AffirmVirtualCard\SessionRegistry;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Exception\LocalizedException;

class CreateAffirmVirtualCardSession extends Action implements HttpPostActionInterface
{
	private Session $checkoutSession;
	private CreateSession $createSession;
	private SessionRegistry $registry;
	private JsonFactory $jsonFactory;
	private Validator $formKeyValidator;
	private MultiLevelLogger $logger;

	public function __construct(Context $context, Session $checkoutSession, CreateSession $createSession,
		SessionRegistry $registry, JsonFactory $jsonFactory, Validator $formKeyValidator, MultiLevelLogger $logger)
	{
		parent::__construct($context);
		$this->checkoutSession = $checkoutSession;
		$this->createSession = $createSession;
		$this->registry = $registry;
		$this->jsonFactory = $jsonFactory;
		$this->formKeyValidator = $formKeyValidator;
		$this->logger = $logger;
	}

	public function execute()
	{
		$result = $this->jsonFactory->create();
		$result->setHeader('Cache-Control', 'no-store, private', true);
		try {
			if (!$this->formKeyValidator->validate($this->getRequest())
				|| $this->getRequest()->getParam('paymentCode') !== Config::CODE) {
				throw new LocalizedException(__('Invalid checkout request.'));
			}
			$quote = $this->checkoutSession->getQuote();
			if (!$quote->getId() || !$quote->getIsActive()) {
				throw new LocalizedException(__('No active checkout is available.'));
			}
			if ($this->getRequest()->getParam('action') === 'invalidate') {
				$reference = $this->getRequest()->getParam('sessionId');
				if (!is_string($reference) || $reference === '') {
					throw new LocalizedException(__('Invalid checkout request.'));
				}
				$this->registry->invalidate((int) $quote->getId(), $reference);
				return $result->setData(['success' => true]);
			}
			if ($this->getRequest()->getParam('action') !== 'create') {
				throw new LocalizedException(__('Invalid checkout request.'));
			}
			$quote->collectTotals();
			return $result->setData($this->createSession->execute($quote));
		} catch (\Throwable $exception) {
			// Never log raw upstream responses, exception contents, JWTs or SDK callbacks.
			$this->logger->logError(1, 'Affirm Virtual Card session request failed.');
			return $result->setHttpResponseCode(400)->setData([
				'message' => (string) __('Unable to initialize Affirm Virtual Card. Please retry or choose another payment method.'),
			]);
		}
	}
}

