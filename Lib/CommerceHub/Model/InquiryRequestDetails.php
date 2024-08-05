<?php
/**
 * InquiryRequestDetails
 *
 * PHP version 7.4
 *
 * @category Class
 * @package  Fiserv\Payments\Lib\CommerceHub
 * @author   OpenAPI Generator team
 * @link     https://openapi-generator.tech
 */

/**
 * Payments
 *
 * This API Specification is designed to provide technical guidance required to consume and integrate with our APIs for payment processing.
 *
 * The version of the OpenAPI document: v1.3.0
 * Generated by: https://openapi-generator.tech
 * OpenAPI Generator version: 6.2.1
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
 * InquiryRequestDetails Class Doc Comment
 *
 * @category Class
 * @package  Fiserv\Payments\Lib\CommerceHub
 * @author   OpenAPI Generator team
 * @link     https://openapi-generator.tech
 * @implements \ArrayAccess<string, mixed>
 */
class InquiryRequestDetails implements ModelInterface, ArrayAccess, \JsonSerializable
{
    public const DISCRIMINATOR = null;

    /**
      * The original name of the model.
      *
      * @var string
      */
    protected static $openAPIModelName = 'InquiryRequestDetails';

    /**
      * Array of property to type mappings. Used for (de)serialization
      *
      * @var string[]
      */
    protected static $openAPITypes = [
        'client_request_id' => 'string',
        'primary_transaction_id' => 'string',
        'primary_order_id' => 'string',
        'merchant_transaction_id' => 'string',
        'merchant_order_id' => 'string'
    ];

    /**
      * Array of property to format mappings. Used for (de)serialization
      *
      * @var string[]
      * @phpstan-var array<string, string|null>
      * @psalm-var array<string, string|null>
      */
    protected static $openAPIFormats = [
        'client_request_id' => null,
        'primary_transaction_id' => null,
        'primary_order_id' => null,
        'merchant_transaction_id' => null,
        'merchant_order_id' => null
    ];

    /**
      * Array of nullable properties. Used for (de)serialization
      *
      * @var boolean[]
      */
    protected static array $openAPINullables = [
        'client_request_id' => false,
		'primary_transaction_id' => false,
		'primary_order_id' => false,
		'merchant_transaction_id' => false,
		'merchant_order_id' => false
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
        'client_request_id' => 'clientRequestId',
        'primary_transaction_id' => 'primaryTransactionId',
        'primary_order_id' => 'primaryOrderId',
        'merchant_transaction_id' => 'merchantTransactionId',
        'merchant_order_id' => 'merchantOrderId'
    ];

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @var string[]
     */
    protected static $setters = [
        'client_request_id' => 'setClientRequestId',
        'primary_transaction_id' => 'setPrimaryTransactionId',
        'primary_order_id' => 'setPrimaryOrderId',
        'merchant_transaction_id' => 'setMerchantTransactionId',
        'merchant_order_id' => 'setMerchantOrderId'
    ];

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @var string[]
     */
    protected static $getters = [
        'client_request_id' => 'getClientRequestId',
        'primary_transaction_id' => 'getPrimaryTransactionId',
        'primary_order_id' => 'getPrimaryOrderId',
        'merchant_transaction_id' => 'getMerchantTransactionId',
        'merchant_order_id' => 'getMerchantOrderId'
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
        $this->setIfExists('client_request_id', $data ?? [], null);
        $this->setIfExists('primary_transaction_id', $data ?? [], null);
        $this->setIfExists('primary_order_id', $data ?? [], null);
        $this->setIfExists('merchant_transaction_id', $data ?? [], null);
        $this->setIfExists('merchant_order_id', $data ?? [], null);
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

        if (!is_null($this->container['client_request_id']) && (mb_strlen($this->container['client_request_id']) > 64)) {
            $invalidProperties[] = "invalid value for 'client_request_id', the character length must be smaller than or equal to 64.";
        }

        if (!is_null($this->container['primary_transaction_id']) && (mb_strlen($this->container['primary_transaction_id']) > 40)) {
            $invalidProperties[] = "invalid value for 'primary_transaction_id', the character length must be smaller than or equal to 40.";
        }

        if (!is_null($this->container['primary_order_id']) && (mb_strlen($this->container['primary_order_id']) > 40)) {
            $invalidProperties[] = "invalid value for 'primary_order_id', the character length must be smaller than or equal to 40.";
        }

        if (!is_null($this->container['merchant_transaction_id']) && (mb_strlen($this->container['merchant_transaction_id']) > 32)) {
            $invalidProperties[] = "invalid value for 'merchant_transaction_id', the character length must be smaller than or equal to 32.";
        }

        if (!is_null($this->container['merchant_order_id']) && (mb_strlen($this->container['merchant_order_id']) > 32)) {
            $invalidProperties[] = "invalid value for 'merchant_order_id', the character length must be smaller than or equal to 32.";
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
     * Gets client_request_id
     *
     * @return string|null
     */
    public function getClientRequestId()
    {
        return $this->container['client_request_id'];
    }

    /**
     * Sets client_request_id
     *
     * @param string|null $client_request_id A client-generated ID for request tracking and signature creation, unique per request. This is also used for idempotency control. Recommended 128-bit UUID format.
     *
     * @return self
     */
    public function setClientRequestId($client_request_id)
    {
        if (!is_null($client_request_id) && (mb_strlen($client_request_id) > 64)) {
            throw new \InvalidArgumentException('invalid length for $client_request_id when calling InquiryRequestDetails., must be smaller than or equal to 64.');
        }


        if (is_null($client_request_id)) {
            throw new \InvalidArgumentException('non-nullable client_request_id cannot be null');
        }

        $this->container['client_request_id'] = $client_request_id;

        return $this;
    }

    /**
     * Gets primary_transaction_id
     *
     * @return string|null
     */
    public function getPrimaryTransactionId()
    {
        return $this->container['primary_transaction_id'];
    }

    /**
     * Sets primary_transaction_id
     *
     * @param string|null $primary_transaction_id Unique identifier for each transaction on the gateway. This value will be populated for the secondary transaction from the path.
     *
     * @return self
     */
    public function setPrimaryTransactionId($primary_transaction_id)
    {
        if (!is_null($primary_transaction_id) && (mb_strlen($primary_transaction_id) > 40)) {
            throw new \InvalidArgumentException('invalid length for $primary_transaction_id when calling InquiryRequestDetails., must be smaller than or equal to 40.');
        }


        if (is_null($primary_transaction_id)) {
            throw new \InvalidArgumentException('non-nullable primary_transaction_id cannot be null');
        }

        $this->container['primary_transaction_id'] = $primary_transaction_id;

        return $this;
    }

    /**
     * Gets primary_order_id
     *
     * @return string|null
     */
    public function getPrimaryOrderId()
    {
        return $this->container['primary_order_id'];
    }

    /**
     * Sets primary_order_id
     *
     * @param string|null $primary_order_id Unique order identifier for each transaction on the gateway. This value will be populated for the secondary transaction from the path.
     *
     * @return self
     */
    public function setPrimaryOrderId($primary_order_id)
    {
        if (!is_null($primary_order_id) && (mb_strlen($primary_order_id) > 40)) {
            throw new \InvalidArgumentException('invalid length for $primary_order_id when calling InquiryRequestDetails., must be smaller than or equal to 40.');
        }


        if (is_null($primary_order_id)) {
            throw new \InvalidArgumentException('non-nullable primary_order_id cannot be null');
        }

        $this->container['primary_order_id'] = $primary_order_id;

        return $this;
    }

    /**
     * Gets merchant_transaction_id
     *
     * @return string|null
     */
    public function getMerchantTransactionId()
    {
        return $this->container['merchant_transaction_id'];
    }

    /**
     * Sets merchant_transaction_id
     *
     * @param string|null $merchant_transaction_id Merchant transaction ID (aka transaction reference ID).
     *
     * @return self
     */
    public function setMerchantTransactionId($merchant_transaction_id)
    {
        if (!is_null($merchant_transaction_id) && (mb_strlen($merchant_transaction_id) > 32)) {
            throw new \InvalidArgumentException('invalid length for $merchant_transaction_id when calling InquiryRequestDetails., must be smaller than or equal to 32.');
        }


        if (is_null($merchant_transaction_id)) {
            throw new \InvalidArgumentException('non-nullable merchant_transaction_id cannot be null');
        }

        $this->container['merchant_transaction_id'] = $merchant_transaction_id;

        return $this;
    }

    /**
     * Gets merchant_order_id
     *
     * @return string|null
     */
    public function getMerchantOrderId()
    {
        return $this->container['merchant_order_id'];
    }

    /**
     * Sets merchant_order_id
     *
     * @param string|null $merchant_order_id Merchant order ID (aka customer reference number or purchase order number).
     *
     * @return self
     */
    public function setMerchantOrderId($merchant_order_id)
    {
        if (!is_null($merchant_order_id) && (mb_strlen($merchant_order_id) > 32)) {
            throw new \InvalidArgumentException('invalid length for $merchant_order_id when calling InquiryRequestDetails., must be smaller than or equal to 32.');
        }


        if (is_null($merchant_order_id)) {
            throw new \InvalidArgumentException('non-nullable merchant_order_id cannot be null');
        }

        $this->container['merchant_order_id'] = $merchant_order_id;

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

