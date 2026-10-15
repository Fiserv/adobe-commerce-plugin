<?php
namespace Fiserv\Payments\Plugin\CashApp;

use Fiserv\Payments\Model\Adapter\CommerceHub\ChHttpAdapter;
use Fiserv\Payments\Model\CashApp\SessionPolicy;

class SessionRequest
{
	private $policy;

	public function __construct(SessionPolicy $policy)
	{
		$this->policy = $policy;
	}

	public function beforeSendRequest(ChHttpAdapter $subject, $data, $endpoint): array
	{
		return [$this->policy->contribute($data, $endpoint), $endpoint];
	}
}
