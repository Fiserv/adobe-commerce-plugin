<?php

namespace Fiserv\Payments\Model\Adapter\CommerceHub;

class ChHttpResponse
{
	/**
	 * @var int
	 */
	private $statusCode;

	/**
	 * @var string
	 */
	private $curlResponse;

	/**
	 * @var int
	 */
	private $headerLength;

	/**
	 * Constructor
	 *
	 * @param Config $config
	 */
	public function __construct(
		$statusCode,
		$curlResponse,
		$headerLength
	) {
		$this->statusCode = $statusCode;
		$this->curlResponse = $curlResponse;
		$this->headerLength = $headerLength;
	}

	public function getStatusCode()
	{
		return $this->statusCode;
	}

	public function getResponse()
	{
		return $this->curlResponse;
	}

	public function getHeaderLength()
	{
		return $this->headerLength;
	}

	public function getHeaders()
	{
		return substr($this->getResponse(), 0, $this->getHeaderLength());
	}

	public function getBody() 
	{
		return substr($this->getResponse(), $this->getHeaderLength());
	}

}
	
