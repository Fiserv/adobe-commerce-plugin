<?php
/**
 * AdditionalData3DS
 *
 * PHP version 7.4
 *
 * @category Class
 * @package  Fiserv\Payments\Lib\CommerceHub
 * @author   OpenAPI Generator team
 * @link     https://openapi-generator.tech
 */

/**
 * Commerce Hub
 *
 * This API Specification is designed to provide technical guidance required to consume and integrate with our APIs for payment processing.
 *
 * The version of the OpenAPI document: v1.24.08
 * Generated by: https://openapi-generator.tech
 * OpenAPI Generator version: 7.3.0
 */

/**
 * NOTE: This class is auto generated by OpenAPI Generator (https://openapi-generator.tech).
 * https://openapi-generator.tech
 * Do not edit the class manually.
 */

namespace Fiserv\Payments\Lib\CommerceHub\Model;

use \ArrayAccess;
use \Fiserv\Payments\Lib\CommerceHub\ObjectSerializer;

/**
 * AdditionalData3DS Class Doc Comment
 *
 * @category Class
 * @description &lt;a href&#x3D;\&quot;../docs?path&#x3D;docs/Resources/Master-Data/Additional-Data-3DS.md\&quot;&gt;Additional data&lt;/a&gt; passed during a 3-D Secure (3DS) authentication.
 * @package  Fiserv\Payments\Lib\CommerceHub
 * @author   OpenAPI Generator team
 * @link     https://openapi-generator.tech
 * @implements \ArrayAccess<string, mixed>
 */
class AdditionalData3DS implements ModelInterface, ArrayAccess, \JsonSerializable
{
    public const DISCRIMINATOR = null;

    /**
      * The original name of the model.
      *
      * @var string
      */
    protected static $openAPIModelName = 'AdditionalData3DS';

    /**
      * Array of property to type mappings. Used for (de)serialization
      *
      * @var string[]
      */
    protected static $openAPITypes = [
        'acs_reference_number' => 'string',
        'acs_transaction_id' => 'string',
        'acs_url' => 'string',
        'authentication_status' => 'string',
        'authentication_attempt_result' => 'string',
        'challenge_indicator' => 'bool',
        'challenge_status' => 'string',
        'challenge_token' => 'string',
        'channel' => 'string',
        'ds_transaction_id' => 'string',
        'server_transaction_id' => 'string',
        'service_provider' => 'string',
        'service_provider_reference_id' => 'string',
        'service_provider_transaction_id' => 'string',
        'status_reason' => 'string',
        'step_up_url' => 'string',
        'override_floor_limit' => 'bool',
        'method_data' => '\Fiserv\Payments\Lib\CommerceHub\Model\MethodData3DS',
        'mpi_data' => '\Fiserv\Payments\Lib\CommerceHub\Model\MpiData3DS',
        'version_data' => '\Fiserv\Payments\Lib\CommerceHub\Model\VersionData3DS'
    ];

    /**
      * Array of property to format mappings. Used for (de)serialization
      *
      * @var string[]
      * @phpstan-var array<string, string|null>
      * @psalm-var array<string, string|null>
      */
    protected static $openAPIFormats = [
        'acs_reference_number' => null,
        'acs_transaction_id' => null,
        'acs_url' => null,
        'authentication_status' => null,
        'authentication_attempt_result' => null,
        'challenge_indicator' => null,
        'challenge_status' => null,
        'challenge_token' => null,
        'channel' => null,
        'ds_transaction_id' => null,
        'server_transaction_id' => null,
        'service_provider' => null,
        'service_provider_reference_id' => null,
        'service_provider_transaction_id' => null,
        'status_reason' => null,
        'step_up_url' => null,
        'override_floor_limit' => null,
        'method_data' => null,
        'mpi_data' => null,
        'version_data' => null
    ];

    /**
      * Array of nullable properties. Used for (de)serialization
      *
      * @var boolean[]
      */
    protected static array $openAPINullables = [
        'acs_reference_number' => false,
        'acs_transaction_id' => false,
        'acs_url' => false,
        'authentication_status' => false,
        'authentication_attempt_result' => false,
        'challenge_indicator' => false,
        'challenge_status' => false,
        'challenge_token' => false,
        'channel' => false,
        'ds_transaction_id' => false,
        'server_transaction_id' => false,
        'service_provider' => false,
        'service_provider_reference_id' => false,
        'service_provider_transaction_id' => false,
        'status_reason' => false,
        'step_up_url' => false,
        'override_floor_limit' => false,
        'method_data' => false,
        'mpi_data' => false,
        'version_data' => false
    ];

    /**
      * If a nullable field gets set to null, insert it here
      *
      * @var boolean[]
      */
    protected array $openAPINullablesSetToNull = [];

    /**
     * Array of property to type mappings. Used for (de)serialization
     *
     * @return array
     */
    public static function openAPITypes()
    {
        return self::$openAPITypes;
    }

    /**
     * Array of property to format mappings. Used for (de)serialization
     *
     * @return array
     */
    public static function openAPIFormats()
    {
        return self::$openAPIFormats;
    }

    /**
     * Array of nullable properties
     *
     * @return array
     */
    protected static function openAPINullables(): array
    {
        return self::$openAPINullables;
    }

    /**
     * Array of nullable field names deliberately set to null
     *
     * @return boolean[]
     */
    private function getOpenAPINullablesSetToNull(): array
    {
        return $this->openAPINullablesSetToNull;
    }

    /**
     * Setter - Array of nullable field names deliberately set to null
     *
     * @param boolean[] $openAPINullablesSetToNull
     */
    private function setOpenAPINullablesSetToNull(array $openAPINullablesSetToNull): void
    {
        $this->openAPINullablesSetToNull = $openAPINullablesSetToNull;
    }

    /**
     * Checks if a property is nullable
     *
     * @param string $property
     * @return bool
     */
    public static function isNullable(string $property): bool
    {
        return self::openAPINullables()[$property] ?? false;
    }

    /**
     * Checks if a nullable property is set to null.
     *
     * @param string $property
     * @return bool
     */
    public function isNullableSetToNull(string $property): bool
    {
        return in_array($property, $this->getOpenAPINullablesSetToNull(), true);
    }

    /**
     * Array of attributes where the key is the local name,
     * and the value is the original name
     *
     * @var string[]
     */
    protected static $attributeMap = [
        'acs_reference_number' => 'acsReferenceNumber',
        'acs_transaction_id' => 'acsTransactionId',
        'acs_url' => 'acsUrl',
        'authentication_status' => 'authenticationStatus',
        'authentication_attempt_result' => 'authenticationAttemptResult',
        'challenge_indicator' => 'challengeIndicator',
        'challenge_status' => 'challengeStatus',
        'challenge_token' => 'challengeToken',
        'channel' => 'channel',
        'ds_transaction_id' => 'dsTransactionId',
        'server_transaction_id' => 'serverTransactionId',
        'service_provider' => 'serviceProvider',
        'service_provider_reference_id' => 'serviceProviderReferenceId',
        'service_provider_transaction_id' => 'serviceProviderTransactionId',
        'status_reason' => 'statusReason',
        'step_up_url' => 'stepUpUrl',
        'override_floor_limit' => 'overrideFloorLimit',
        'method_data' => 'methodData',
        'mpi_data' => 'mpiData',
        'version_data' => 'versionData'
    ];

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @var string[]
     */
    protected static $setters = [
        'acs_reference_number' => 'setAcsReferenceNumber',
        'acs_transaction_id' => 'setAcsTransactionId',
        'acs_url' => 'setAcsUrl',
        'authentication_status' => 'setAuthenticationStatus',
        'authentication_attempt_result' => 'setAuthenticationAttemptResult',
        'challenge_indicator' => 'setChallengeIndicator',
        'challenge_status' => 'setChallengeStatus',
        'challenge_token' => 'setChallengeToken',
        'channel' => 'setChannel',
        'ds_transaction_id' => 'setDsTransactionId',
        'server_transaction_id' => 'setServerTransactionId',
        'service_provider' => 'setServiceProvider',
        'service_provider_reference_id' => 'setServiceProviderReferenceId',
        'service_provider_transaction_id' => 'setServiceProviderTransactionId',
        'status_reason' => 'setStatusReason',
        'step_up_url' => 'setStepUpUrl',
        'override_floor_limit' => 'setOverrideFloorLimit',
        'method_data' => 'setMethodData',
        'mpi_data' => 'setMpiData',
        'version_data' => 'setVersionData'
    ];

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @var string[]
     */
    protected static $getters = [
        'acs_reference_number' => 'getAcsReferenceNumber',
        'acs_transaction_id' => 'getAcsTransactionId',
        'acs_url' => 'getAcsUrl',
        'authentication_status' => 'getAuthenticationStatus',
        'authentication_attempt_result' => 'getAuthenticationAttemptResult',
        'challenge_indicator' => 'getChallengeIndicator',
        'challenge_status' => 'getChallengeStatus',
        'challenge_token' => 'getChallengeToken',
        'channel' => 'getChannel',
        'ds_transaction_id' => 'getDsTransactionId',
        'server_transaction_id' => 'getServerTransactionId',
        'service_provider' => 'getServiceProvider',
        'service_provider_reference_id' => 'getServiceProviderReferenceId',
        'service_provider_transaction_id' => 'getServiceProviderTransactionId',
        'status_reason' => 'getStatusReason',
        'step_up_url' => 'getStepUpUrl',
        'override_floor_limit' => 'getOverrideFloorLimit',
        'method_data' => 'getMethodData',
        'mpi_data' => 'getMpiData',
        'version_data' => 'getVersionData'
    ];

    /**
     * Array of attributes where the key is the local name,
     * and the value is the original name
     *
     * @return array
     */
    public static function attributeMap()
    {
        return self::$attributeMap;
    }

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @return array
     */
    public static function setters()
    {
        return self::$setters;
    }

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @return array
     */
    public static function getters()
    {
        return self::$getters;
    }

    /**
     * The original name of the model.
     *
     * @return string
     */
    public function getModelName()
    {
        return self::$openAPIModelName;
    }


    /**
     * Associative array for storing property values
     *
     * @var mixed[]
     */
    protected $container = [];

    /**
     * Constructor
     *
     * @param mixed[] $data Associated array of property values
     *                      initializing the model
     */
    public function __construct(array $data = null)
    {
        $this->setIfExists('acs_reference_number', $data ?? [], null);
        $this->setIfExists('acs_transaction_id', $data ?? [], null);
        $this->setIfExists('acs_url', $data ?? [], null);
        $this->setIfExists('authentication_status', $data ?? [], null);
        $this->setIfExists('authentication_attempt_result', $data ?? [], null);
        $this->setIfExists('challenge_indicator', $data ?? [], null);
        $this->setIfExists('challenge_status', $data ?? [], null);
        $this->setIfExists('challenge_token', $data ?? [], null);
        $this->setIfExists('channel', $data ?? [], null);
        $this->setIfExists('ds_transaction_id', $data ?? [], null);
        $this->setIfExists('server_transaction_id', $data ?? [], null);
        $this->setIfExists('service_provider', $data ?? [], null);
        $this->setIfExists('service_provider_reference_id', $data ?? [], null);
        $this->setIfExists('service_provider_transaction_id', $data ?? [], null);
        $this->setIfExists('status_reason', $data ?? [], null);
        $this->setIfExists('step_up_url', $data ?? [], null);
        $this->setIfExists('override_floor_limit', $data ?? [], null);
        $this->setIfExists('method_data', $data ?? [], null);
        $this->setIfExists('mpi_data', $data ?? [], null);
        $this->setIfExists('version_data', $data ?? [], null);
    }

    /**
    * Sets $this->container[$variableName] to the given data or to the given default Value; if $variableName
    * is nullable and its value is set to null in the $fields array, then mark it as "set to null" in the
    * $this->openAPINullablesSetToNull array
    *
    * @param string $variableName
    * @param array  $fields
    * @param mixed  $defaultValue
    */
    private function setIfExists(string $variableName, array $fields, $defaultValue): void
    {
        if (self::isNullable($variableName) && array_key_exists($variableName, $fields) && is_null($fields[$variableName])) {
            $this->openAPINullablesSetToNull[] = $variableName;
        }

        $this->container[$variableName] = $fields[$variableName] ?? $defaultValue;
    }

    /**
     * Show all the invalid properties with reasons.
     *
     * @return array invalid properties with reasons
     */
    public function listInvalidProperties()
    {
        $invalidProperties = [];

        if (!is_null($this->container['acs_reference_number']) && (mb_strlen($this->container['acs_reference_number']) > 60)) {
            $invalidProperties[] = "invalid value for 'acs_reference_number', the character length must be smaller than or equal to 60.";
        }

        if (!is_null($this->container['acs_transaction_id']) && (mb_strlen($this->container['acs_transaction_id']) > 60)) {
            $invalidProperties[] = "invalid value for 'acs_transaction_id', the character length must be smaller than or equal to 60.";
        }

        if (!is_null($this->container['acs_url']) && (mb_strlen($this->container['acs_url']) > 256)) {
            $invalidProperties[] = "invalid value for 'acs_url', the character length must be smaller than or equal to 256.";
        }

        if (!is_null($this->container['authentication_status']) && (mb_strlen($this->container['authentication_status']) > 2)) {
            $invalidProperties[] = "invalid value for 'authentication_status', the character length must be smaller than or equal to 2.";
        }

        if (!is_null($this->container['authentication_attempt_result']) && (mb_strlen($this->container['authentication_attempt_result']) > 1024)) {
            $invalidProperties[] = "invalid value for 'authentication_attempt_result', the character length must be smaller than or equal to 1024.";
        }

        if (!is_null($this->container['challenge_status']) && (mb_strlen($this->container['challenge_status']) > 2)) {
            $invalidProperties[] = "invalid value for 'challenge_status', the character length must be smaller than or equal to 2.";
        }

        if (!is_null($this->container['challenge_token']) && (mb_strlen($this->container['challenge_token']) > 1024)) {
            $invalidProperties[] = "invalid value for 'challenge_token', the character length must be smaller than or equal to 1024.";
        }

        if (!is_null($this->container['channel']) && (mb_strlen($this->container['channel']) > 32)) {
            $invalidProperties[] = "invalid value for 'channel', the character length must be smaller than or equal to 32.";
        }

        if (!is_null($this->container['ds_transaction_id']) && (mb_strlen($this->container['ds_transaction_id']) > 60)) {
            $invalidProperties[] = "invalid value for 'ds_transaction_id', the character length must be smaller than or equal to 60.";
        }

        if (!is_null($this->container['server_transaction_id']) && (mb_strlen($this->container['server_transaction_id']) > 60)) {
            $invalidProperties[] = "invalid value for 'server_transaction_id', the character length must be smaller than or equal to 60.";
        }

        if (!is_null($this->container['service_provider']) && (mb_strlen($this->container['service_provider']) > 32)) {
            $invalidProperties[] = "invalid value for 'service_provider', the character length must be smaller than or equal to 32.";
        }

        if (!is_null($this->container['service_provider_reference_id']) && (mb_strlen($this->container['service_provider_reference_id']) > 60)) {
            $invalidProperties[] = "invalid value for 'service_provider_reference_id', the character length must be smaller than or equal to 60.";
        }

        if (!is_null($this->container['service_provider_transaction_id']) && (mb_strlen($this->container['service_provider_transaction_id']) > 60)) {
            $invalidProperties[] = "invalid value for 'service_provider_transaction_id', the character length must be smaller than or equal to 60.";
        }

        if (!is_null($this->container['status_reason']) && (mb_strlen($this->container['status_reason']) > 16)) {
            $invalidProperties[] = "invalid value for 'status_reason', the character length must be smaller than or equal to 16.";
        }

        if (!is_null($this->container['step_up_url']) && (mb_strlen($this->container['step_up_url']) > 256)) {
            $invalidProperties[] = "invalid value for 'step_up_url', the character length must be smaller than or equal to 256.";
        }

        return $invalidProperties;
    }

    /**
     * Validate all the properties in the model
     * return true if all passed
     *
     * @return bool True if all properties are valid
     */
    public function valid()
    {
        return count($this->listInvalidProperties()) === 0;
    }


    /**
     * Gets acs_reference_number
     *
     * @return string|null
     */
    public function getAcsReferenceNumber()
    {
        return $this->container['acs_reference_number'];
    }

    /**
     * Sets acs_reference_number
     *
     * @param string|null $acs_reference_number Unique reference number assigned by the Access Control Server (ACS) to identify a single transaction.
     *
     * @return self
     */
    public function setAcsReferenceNumber($acs_reference_number)
    {
        if (is_null($acs_reference_number)) {
            throw new \InvalidArgumentException('non-nullable acs_reference_number cannot be null');
        }
        if ((mb_strlen($acs_reference_number) > 60)) {
            throw new \InvalidArgumentException('invalid length for $acs_reference_number when calling AdditionalData3DS., must be smaller than or equal to 60.');
        }

        $this->container['acs_reference_number'] = $acs_reference_number;

        return $this;
    }

    /**
     * Gets acs_transaction_id
     *
     * @return string|null
     */
    public function getAcsTransactionId()
    {
        return $this->container['acs_transaction_id'];
    }

    /**
     * Sets acs_transaction_id
     *
     * @param string|null $acs_transaction_id Unique transaction identifier assigned by the Access Control Server (ACS) to identify a single transaction.
     *
     * @return self
     */
    public function setAcsTransactionId($acs_transaction_id)
    {
        if (is_null($acs_transaction_id)) {
            throw new \InvalidArgumentException('non-nullable acs_transaction_id cannot be null');
        }
        if ((mb_strlen($acs_transaction_id) > 60)) {
            throw new \InvalidArgumentException('invalid length for $acs_transaction_id when calling AdditionalData3DS., must be smaller than or equal to 60.');
        }

        $this->container['acs_transaction_id'] = $acs_transaction_id;

        return $this;
    }

    /**
     * Gets acs_url
     *
     * @return string|null
     */
    public function getAcsUrl()
    {
        return $this->container['acs_url'];
    }

    /**
     * Sets acs_url
     *
     * @param string|null $acs_url The URL to redirect the Consumer to complete the Consumer Authentication transaction.
     *
     * @return self
     */
    public function setAcsUrl($acs_url)
    {
        if (is_null($acs_url)) {
            throw new \InvalidArgumentException('non-nullable acs_url cannot be null');
        }
        if ((mb_strlen($acs_url) > 256)) {
            throw new \InvalidArgumentException('invalid length for $acs_url when calling AdditionalData3DS., must be smaller than or equal to 256.');
        }

        $this->container['acs_url'] = $acs_url;

        return $this;
    }

    /**
     * Gets authentication_status
     *
     * @return string|null
     */
    public function getAuthenticationStatus()
    {
        return $this->container['authentication_status'];
    }

    /**
     * Sets authentication_status
     *
     * @param string|null $authentication_status The result of authentication attempt returned by the 3D Secure authentication process (PaRes).
     *
     * @return self
     */
    public function setAuthenticationStatus($authentication_status)
    {
        if (is_null($authentication_status)) {
            throw new \InvalidArgumentException('non-nullable authentication_status cannot be null');
        }
        if ((mb_strlen($authentication_status) > 2)) {
            throw new \InvalidArgumentException('invalid length for $authentication_status when calling AdditionalData3DS., must be smaller than or equal to 2.');
        }

        $this->container['authentication_status'] = $authentication_status;

        return $this;
    }

    /**
     * Gets authentication_attempt_result
     *
     * @return string|null
     */
    public function getAuthenticationAttemptResult()
    {
        return $this->container['authentication_attempt_result'];
    }

    /**
     * Sets authentication_attempt_result
     *
     * @param string|null $authentication_attempt_result Result of authentication attempt from Payer Authentication Response (PaRes). 3DS 1.x
     *
     * @return self
     */
    public function setAuthenticationAttemptResult($authentication_attempt_result)
    {
        if (is_null($authentication_attempt_result)) {
            throw new \InvalidArgumentException('non-nullable authentication_attempt_result cannot be null');
        }
        if ((mb_strlen($authentication_attempt_result) > 1024)) {
            throw new \InvalidArgumentException('invalid length for $authentication_attempt_result when calling AdditionalData3DS., must be smaller than or equal to 1024.');
        }

        $this->container['authentication_attempt_result'] = $authentication_attempt_result;

        return $this;
    }

    /**
     * Gets challenge_indicator
     *
     * @return bool|null
     */
    public function getChallengeIndicator()
    {
        return $this->container['challenge_indicator'];
    }

    /**
     * Sets challenge_indicator
     *
     * @param bool|null $challenge_indicator Indicator that forces a customer to complete a transaction using 3-D Secure (3DS) if available.
     *
     * @return self
     */
    public function setChallengeIndicator($challenge_indicator)
    {
        if (is_null($challenge_indicator)) {
            throw new \InvalidArgumentException('non-nullable challenge_indicator cannot be null');
        }
        $this->container['challenge_indicator'] = $challenge_indicator;

        return $this;
    }

    /**
     * Gets challenge_status
     *
     * @return string|null
     */
    public function getChallengeStatus()
    {
        return $this->container['challenge_status'];
    }

    /**
     * Sets challenge_status
     *
     * @param string|null $challenge_status The transaction status as returned by the 3D Secure authentication process. (CRes)
     *
     * @return self
     */
    public function setChallengeStatus($challenge_status)
    {
        if (is_null($challenge_status)) {
            throw new \InvalidArgumentException('non-nullable challenge_status cannot be null');
        }
        if ((mb_strlen($challenge_status) > 2)) {
            throw new \InvalidArgumentException('invalid length for $challenge_status when calling AdditionalData3DS., must be smaller than or equal to 2.');
        }

        $this->container['challenge_status'] = $challenge_status;

        return $this;
    }

    /**
     * Gets challenge_token
     *
     * @return string|null
     */
    public function getChallengeToken()
    {
        return $this->container['challenge_token'];
    }

    /**
     * Sets challenge_token
     *
     * @param string|null $challenge_token Java web token for 3-D Secure.
     *
     * @return self
     */
    public function setChallengeToken($challenge_token)
    {
        if (is_null($challenge_token)) {
            throw new \InvalidArgumentException('non-nullable challenge_token cannot be null');
        }
        if ((mb_strlen($challenge_token) > 1024)) {
            throw new \InvalidArgumentException('invalid length for $challenge_token when calling AdditionalData3DS., must be smaller than or equal to 1024.');
        }

        $this->container['challenge_token'] = $challenge_token;

        return $this;
    }

    /**
     * Gets channel
     *
     * @return string|null
     */
    public function getChannel()
    {
        return $this->container['channel'];
    }

    /**
     * Sets channel
     *
     * @param string|null $channel Determines the <a href=\"../docs?path=docs/Resources/Master-Data/Additional-Data-3DS.md\">channel</a> that the transaction came through.
     *
     * @return self
     */
    public function setChannel($channel)
    {
        if (is_null($channel)) {
            throw new \InvalidArgumentException('non-nullable channel cannot be null');
        }
        if ((mb_strlen($channel) > 32)) {
            throw new \InvalidArgumentException('invalid length for $channel when calling AdditionalData3DS., must be smaller than or equal to 32.');
        }

        $this->container['channel'] = $channel;

        return $this;
    }

    /**
     * Gets ds_transaction_id
     *
     * @return string|null
     */
    public function getDsTransactionId()
    {
        return $this->container['ds_transaction_id'];
    }

    /**
     * Sets ds_transaction_id
     *
     * @param string|null $ds_transaction_id Unique transaction identifier assigned by the Directory Server (DS) to identify a single transaction
     *
     * @return self
     */
    public function setDsTransactionId($ds_transaction_id)
    {
        if (is_null($ds_transaction_id)) {
            throw new \InvalidArgumentException('non-nullable ds_transaction_id cannot be null');
        }
        if ((mb_strlen($ds_transaction_id) > 60)) {
            throw new \InvalidArgumentException('invalid length for $ds_transaction_id when calling AdditionalData3DS., must be smaller than or equal to 60.');
        }

        $this->container['ds_transaction_id'] = $ds_transaction_id;

        return $this;
    }

    /**
     * Gets server_transaction_id
     *
     * @return string|null
     */
    public function getServerTransactionId()
    {
        return $this->container['server_transaction_id'];
    }

    /**
     * Sets server_transaction_id
     *
     * @param string|null $server_transaction_id Unique transaction identifier assigned by the 3DS Server to identify a single transaction
     *
     * @return self
     */
    public function setServerTransactionId($server_transaction_id)
    {
        if (is_null($server_transaction_id)) {
            throw new \InvalidArgumentException('non-nullable server_transaction_id cannot be null');
        }
        if ((mb_strlen($server_transaction_id) > 60)) {
            throw new \InvalidArgumentException('invalid length for $server_transaction_id when calling AdditionalData3DS., must be smaller than or equal to 60.');
        }

        $this->container['server_transaction_id'] = $server_transaction_id;

        return $this;
    }

    /**
     * Gets service_provider
     *
     * @return string|null
     */
    public function getServiceProvider()
    {
        return $this->container['service_provider'];
    }

    /**
     * Sets service_provider
     *
     * @param string|null $service_provider The 3DS service provider responsible for managing the 3DS transaction
     *
     * @return self
     */
    public function setServiceProvider($service_provider)
    {
        if (is_null($service_provider)) {
            throw new \InvalidArgumentException('non-nullable service_provider cannot be null');
        }
        if ((mb_strlen($service_provider) > 32)) {
            throw new \InvalidArgumentException('invalid length for $service_provider when calling AdditionalData3DS., must be smaller than or equal to 32.');
        }

        $this->container['service_provider'] = $service_provider;

        return $this;
    }

    /**
     * Gets service_provider_reference_id
     *
     * @return string|null
     */
    public function getServiceProviderReferenceId()
    {
        return $this->container['service_provider_reference_id'];
    }

    /**
     * Sets service_provider_reference_id
     *
     * @param string|null $service_provider_reference_id Unique reference identifier assigned by the 3DS Server during an initialization.
     *
     * @return self
     */
    public function setServiceProviderReferenceId($service_provider_reference_id)
    {
        if (is_null($service_provider_reference_id)) {
            throw new \InvalidArgumentException('non-nullable service_provider_reference_id cannot be null');
        }
        if ((mb_strlen($service_provider_reference_id) > 60)) {
            throw new \InvalidArgumentException('invalid length for $service_provider_reference_id when calling AdditionalData3DS., must be smaller than or equal to 60.');
        }

        $this->container['service_provider_reference_id'] = $service_provider_reference_id;

        return $this;
    }

    /**
     * Gets service_provider_transaction_id
     *
     * @return string|null
     */
    public function getServiceProviderTransactionId()
    {
        return $this->container['service_provider_transaction_id'];
    }

    /**
     * Sets service_provider_transaction_id
     *
     * @param string|null $service_provider_transaction_id Unique transaction identifier assigned by the 3DS Server to identify a single transaction.
     *
     * @return self
     */
    public function setServiceProviderTransactionId($service_provider_transaction_id)
    {
        if (is_null($service_provider_transaction_id)) {
            throw new \InvalidArgumentException('non-nullable service_provider_transaction_id cannot be null');
        }
        if ((mb_strlen($service_provider_transaction_id) > 60)) {
            throw new \InvalidArgumentException('invalid length for $service_provider_transaction_id when calling AdditionalData3DS., must be smaller than or equal to 60.');
        }

        $this->container['service_provider_transaction_id'] = $service_provider_transaction_id;

        return $this;
    }

    /**
     * Gets status_reason
     *
     * @return string|null
     */
    public function getStatusReason()
    {
        return $this->container['status_reason'];
    }

    /**
     * Sets status_reason
     *
     * @param string|null $status_reason Details about a given transaction status.
     *
     * @return self
     */
    public function setStatusReason($status_reason)
    {
        if (is_null($status_reason)) {
            throw new \InvalidArgumentException('non-nullable status_reason cannot be null');
        }
        if ((mb_strlen($status_reason) > 16)) {
            throw new \InvalidArgumentException('invalid length for $status_reason when calling AdditionalData3DS., must be smaller than or equal to 16.');
        }

        $this->container['status_reason'] = $status_reason;

        return $this;
    }

    /**
     * Gets step_up_url
     *
     * @return string|null
     */
    public function getStepUpUrl()
    {
        return $this->container['step_up_url'];
    }

    /**
     * Sets step_up_url
     *
     * @param string|null $step_up_url The URL that the client uses to post the cardholder in order to complete the Consumer Authentication transaction.
     *
     * @return self
     */
    public function setStepUpUrl($step_up_url)
    {
        if (is_null($step_up_url)) {
            throw new \InvalidArgumentException('non-nullable step_up_url cannot be null');
        }
        if ((mb_strlen($step_up_url) > 256)) {
            throw new \InvalidArgumentException('invalid length for $step_up_url when calling AdditionalData3DS., must be smaller than or equal to 256.');
        }

        $this->container['step_up_url'] = $step_up_url;

        return $this;
    }

    /**
     * Gets override_floor_limit
     *
     * @return bool|null
     */
    public function getOverrideFloorLimit()
    {
        return $this->container['override_floor_limit'];
    }

    /**
     * Sets override_floor_limit
     *
     * @param bool|null $override_floor_limit Indicator to override the default configuration to continue 3DS authentication.
     *
     * @return self
     */
    public function setOverrideFloorLimit($override_floor_limit)
    {
        if (is_null($override_floor_limit)) {
            throw new \InvalidArgumentException('non-nullable override_floor_limit cannot be null');
        }
        $this->container['override_floor_limit'] = $override_floor_limit;

        return $this;
    }

    /**
     * Gets method_data
     *
     * @return \Fiserv\Payments\Lib\CommerceHub\Model\MethodData3DS|null
     */
    public function getMethodData()
    {
        return $this->container['method_data'];
    }

    /**
     * Sets method_data
     *
     * @param \Fiserv\Payments\Lib\CommerceHub\Model\MethodData3DS|null $method_data method_data
     *
     * @return self
     */
    public function setMethodData($method_data)
    {
        if (is_null($method_data)) {
            throw new \InvalidArgumentException('non-nullable method_data cannot be null');
        }
        $this->container['method_data'] = $method_data;

        return $this;
    }

    /**
     * Gets mpi_data
     *
     * @return \Fiserv\Payments\Lib\CommerceHub\Model\MpiData3DS|null
     */
    public function getMpiData()
    {
        return $this->container['mpi_data'];
    }

    /**
     * Sets mpi_data
     *
     * @param \Fiserv\Payments\Lib\CommerceHub\Model\MpiData3DS|null $mpi_data mpi_data
     *
     * @return self
     */
    public function setMpiData($mpi_data)
    {
        if (is_null($mpi_data)) {
            throw new \InvalidArgumentException('non-nullable mpi_data cannot be null');
        }
        $this->container['mpi_data'] = $mpi_data;

        return $this;
    }

    /**
     * Gets version_data
     *
     * @return \Fiserv\Payments\Lib\CommerceHub\Model\VersionData3DS|null
     */
    public function getVersionData()
    {
        return $this->container['version_data'];
    }

    /**
     * Sets version_data
     *
     * @param \Fiserv\Payments\Lib\CommerceHub\Model\VersionData3DS|null $version_data version_data
     *
     * @return self
     */
    public function setVersionData($version_data)
    {
        if (is_null($version_data)) {
            throw new \InvalidArgumentException('non-nullable version_data cannot be null');
        }
        $this->container['version_data'] = $version_data;

        return $this;
    }
    /**
     * Returns true if offset exists. False otherwise.
     *
     * @param integer $offset Offset
     *
     * @return boolean
     */
    public function offsetExists($offset): bool
    {
        return isset($this->container[$offset]);
    }

    /**
     * Gets offset.
     *
     * @param integer $offset Offset
     *
     * @return mixed|null
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->container[$offset] ?? null;
    }

    /**
     * Sets value based on offset.
     *
     * @param int|null $offset Offset
     * @param mixed    $value  Value to be set
     *
     * @return void
     */
    public function offsetSet($offset, $value): void
    {
        if (is_null($offset)) {
            $this->container[] = $value;
        } else {
            $this->container[$offset] = $value;
        }
    }

    /**
     * Unsets offset.
     *
     * @param integer $offset Offset
     *
     * @return void
     */
    public function offsetUnset($offset): void
    {
        unset($this->container[$offset]);
    }

    /**
     * Serializes the object to a value that can be serialized natively by json_encode().
     * @link https://www.php.net/manual/en/jsonserializable.jsonserialize.php
     *
     * @return mixed Returns data which can be serialized by json_encode(), which is a value
     * of any type other than a resource.
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
       return ObjectSerializer::sanitizeForSerialization($this);
    }

    /**
     * Gets the string presentation of the object
     *
     * @return string
     */
    public function __toString()
    {
        return json_encode(
            ObjectSerializer::sanitizeForSerialization($this),
            JSON_PRETTY_PRINT
        );
    }

    /**
     * Gets a header-safe presentation of the object
     *
     * @return string
     */
    public function toHeaderValue()
    {
        return json_encode(ObjectSerializer::sanitizeForSerialization($this));
    }
}

