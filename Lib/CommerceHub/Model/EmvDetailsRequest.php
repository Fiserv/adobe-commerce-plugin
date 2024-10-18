<?php
/**
 * EmvDetailsRequest
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
 * EmvDetailsRequest Class Doc Comment
 *
 * @category Class
 * @package  Fiserv\Payments\Lib\CommerceHub
 * @author   OpenAPI Generator team
 * @link     https://openapi-generator.tech
 * @implements \ArrayAccess<string, mixed>
 */
class EmvDetailsRequest implements ModelInterface, ArrayAccess, \JsonSerializable
{
    public const DISCRIMINATOR = null;

    /**
      * The original name of the model.
      *
      * @var string
      */
    protected static $openAPIModelName = 'EmvDetailsRequest';

    /**
      * Array of property to type mappings. Used for (de)serialization
      *
      * @var string[]
      */
    protected static $openAPITypes = [
        'transaction_details' => '\Fiserv\Payments\Lib\CommerceHub\Model\TransactionDetails',
        'emv_details' => '\Fiserv\Payments\Lib\CommerceHub\Model\EmvDetails',
        'merchant_details' => '\Fiserv\Payments\Lib\CommerceHub\Model\MerchantDetails',
        'processor_response_details' => '\Fiserv\Payments\Lib\CommerceHub\Model\ProcessorResponseDetails'
    ];

    /**
      * Array of property to format mappings. Used for (de)serialization
      *
      * @var string[]
      * @phpstan-var array<string, string|null>
      * @psalm-var array<string, string|null>
      */
    protected static $openAPIFormats = [
        'transaction_details' => null,
        'emv_details' => null,
        'merchant_details' => null,
        'processor_response_details' => null
    ];

    /**
      * Array of nullable properties. Used for (de)serialization
      *
      * @var boolean[]
      */
    protected static array $openAPINullables = [
        'transaction_details' => false,
        'emv_details' => false,
        'merchant_details' => false,
        'processor_response_details' => false
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
        'transaction_details' => 'transactionDetails',
        'emv_details' => 'emvDetails',
        'merchant_details' => 'merchantDetails',
        'processor_response_details' => 'processorResponseDetails'
    ];

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @var string[]
     */
    protected static $setters = [
        'transaction_details' => 'setTransactionDetails',
        'emv_details' => 'setEmvDetails',
        'merchant_details' => 'setMerchantDetails',
        'processor_response_details' => 'setProcessorResponseDetails'
    ];

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @var string[]
     */
    protected static $getters = [
        'transaction_details' => 'getTransactionDetails',
        'emv_details' => 'getEmvDetails',
        'merchant_details' => 'getMerchantDetails',
        'processor_response_details' => 'getProcessorResponseDetails'
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
        $this->setIfExists('transaction_details', $data ?? [], null);
        $this->setIfExists('emv_details', $data ?? [], null);
        $this->setIfExists('merchant_details', $data ?? [], null);
        $this->setIfExists('processor_response_details', $data ?? [], null);
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
     * Gets transaction_details
     *
     * @return \Fiserv\Payments\Lib\CommerceHub\Model\TransactionDetails|null
     */
    public function getTransactionDetails()
    {
        return $this->container['transaction_details'];
    }

    /**
     * Sets transaction_details
     *
     * @param \Fiserv\Payments\Lib\CommerceHub\Model\TransactionDetails|null $transaction_details transaction_details
     *
     * @return self
     */
    public function setTransactionDetails($transaction_details)
    {
        if (is_null($transaction_details)) {
            throw new \InvalidArgumentException('non-nullable transaction_details cannot be null');
        }
        $this->container['transaction_details'] = $transaction_details;

        return $this;
    }

    /**
     * Gets emv_details
     *
     * @return \Fiserv\Payments\Lib\CommerceHub\Model\EmvDetails|null
     */
    public function getEmvDetails()
    {
        return $this->container['emv_details'];
    }

    /**
     * Sets emv_details
     *
     * @param \Fiserv\Payments\Lib\CommerceHub\Model\EmvDetails|null $emv_details emv_details
     *
     * @return self
     */
    public function setEmvDetails($emv_details)
    {
        if (is_null($emv_details)) {
            throw new \InvalidArgumentException('non-nullable emv_details cannot be null');
        }
        $this->container['emv_details'] = $emv_details;

        return $this;
    }

    /**
     * Gets merchant_details
     *
     * @return \Fiserv\Payments\Lib\CommerceHub\Model\MerchantDetails|null
     */
    public function getMerchantDetails()
    {
        return $this->container['merchant_details'];
    }

    /**
     * Sets merchant_details
     *
     * @param \Fiserv\Payments\Lib\CommerceHub\Model\MerchantDetails|null $merchant_details merchant_details
     *
     * @return self
     */
    public function setMerchantDetails($merchant_details)
    {
        if (is_null($merchant_details)) {
            throw new \InvalidArgumentException('non-nullable merchant_details cannot be null');
        }
        $this->container['merchant_details'] = $merchant_details;

        return $this;
    }

    /**
     * Gets processor_response_details
     *
     * @return \Fiserv\Payments\Lib\CommerceHub\Model\ProcessorResponseDetails|null
     */
    public function getProcessorResponseDetails()
    {
        return $this->container['processor_response_details'];
    }

    /**
     * Sets processor_response_details
     *
     * @param \Fiserv\Payments\Lib\CommerceHub\Model\ProcessorResponseDetails|null $processor_response_details processor_response_details
     *
     * @return self
     */
    public function setProcessorResponseDetails($processor_response_details)
    {
        if (is_null($processor_response_details)) {
            throw new \InvalidArgumentException('non-nullable processor_response_details cannot be null');
        }
        $this->container['processor_response_details'] = $processor_response_details;

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

