<?php
/**
 * AccountVerificationResponse
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
 * AccountVerificationResponse Class Doc Comment
 *
 * @category Class
 * @package  Fiserv\Payments\Lib\CommerceHub
 * @author   OpenAPI Generator team
 * @link     https://openapi-generator.tech
 * @implements \ArrayAccess<string, mixed>
 */
class AccountVerificationResponse implements ModelInterface, ArrayAccess, \JsonSerializable
{
    public const DISCRIMINATOR = null;

    /**
      * The original name of the model.
      *
      * @var string
      */
    protected static $openAPIModelName = 'AccountVerificationResponse';

    /**
      * Array of property to type mappings. Used for (de)serialization
      *
      * @var string[]
      */
    protected static $openAPITypes = [
        'gateway_response' => '\Fiserv\Payments\Lib\CommerceHub\Model\GatewayResponse',
        'processor_response_details' => '\Fiserv\Payments\Lib\CommerceHub\Model\ProcessorResponseDetails',
        'source' => '\Fiserv\Payments\Lib\CommerceHub\Model\Source',
        'payment_tokens' => '\Fiserv\Payments\Lib\CommerceHub\Model\ResponsePaymentToken[]',
        'billing_address' => '\Fiserv\Payments\Lib\CommerceHub\Model\BillingAddress',
        'customer' => '\Fiserv\Payments\Lib\CommerceHub\Model\Customer',
        'merchant_details' => '\Fiserv\Payments\Lib\CommerceHub\Model\MerchantDetails'
    ];

    /**
      * Array of property to format mappings. Used for (de)serialization
      *
      * @var string[]
      * @phpstan-var array<string, string|null>
      * @psalm-var array<string, string|null>
      */
    protected static $openAPIFormats = [
        'gateway_response' => null,
        'processor_response_details' => null,
        'source' => null,
        'payment_tokens' => null,
        'billing_address' => null,
        'customer' => null,
        'merchant_details' => null
    ];

    /**
      * Array of nullable properties. Used for (de)serialization
      *
      * @var boolean[]
      */
    protected static array $openAPINullables = [
        'gateway_response' => false,
        'processor_response_details' => false,
        'source' => false,
        'payment_tokens' => false,
        'billing_address' => false,
        'customer' => false,
        'merchant_details' => false
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
        'gateway_response' => 'gatewayResponse',
        'processor_response_details' => 'processorResponseDetails',
        'source' => 'source',
        'payment_tokens' => 'paymentTokens',
        'billing_address' => 'billingAddress',
        'customer' => 'customer',
        'merchant_details' => 'merchantDetails'
    ];

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @var string[]
     */
    protected static $setters = [
        'gateway_response' => 'setGatewayResponse',
        'processor_response_details' => 'setProcessorResponseDetails',
        'source' => 'setSource',
        'payment_tokens' => 'setPaymentTokens',
        'billing_address' => 'setBillingAddress',
        'customer' => 'setCustomer',
        'merchant_details' => 'setMerchantDetails'
    ];

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @var string[]
     */
    protected static $getters = [
        'gateway_response' => 'getGatewayResponse',
        'processor_response_details' => 'getProcessorResponseDetails',
        'source' => 'getSource',
        'payment_tokens' => 'getPaymentTokens',
        'billing_address' => 'getBillingAddress',
        'customer' => 'getCustomer',
        'merchant_details' => 'getMerchantDetails'
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
        $this->setIfExists('gateway_response', $data ?? [], null);
        $this->setIfExists('processor_response_details', $data ?? [], null);
        $this->setIfExists('source', $data ?? [], null);
        $this->setIfExists('payment_tokens', $data ?? [], null);
        $this->setIfExists('billing_address', $data ?? [], null);
        $this->setIfExists('customer', $data ?? [], null);
        $this->setIfExists('merchant_details', $data ?? [], null);
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
     * Gets gateway_response
     *
     * @return \Fiserv\Payments\Lib\CommerceHub\Model\GatewayResponse|null
     */
    public function getGatewayResponse()
    {
        return $this->container['gateway_response'];
    }

    /**
     * Sets gateway_response
     *
     * @param \Fiserv\Payments\Lib\CommerceHub\Model\GatewayResponse|null $gateway_response gateway_response
     *
     * @return self
     */
    public function setGatewayResponse($gateway_response)
    {
        if (is_null($gateway_response)) {
            throw new \InvalidArgumentException('non-nullable gateway_response cannot be null');
        }
        $this->container['gateway_response'] = $gateway_response;

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
     * Gets source
     *
     * @return \Fiserv\Payments\Lib\CommerceHub\Model\Source|null
     */
    public function getSource()
    {
        return $this->container['source'];
    }

    /**
     * Sets source
     *
     * @param \Fiserv\Payments\Lib\CommerceHub\Model\Source|null $source source
     *
     * @return self
     */
    public function setSource($source)
    {
        if (is_null($source)) {
            throw new \InvalidArgumentException('non-nullable source cannot be null');
        }
        $this->container['source'] = $source;

        return $this;
    }

    /**
     * Gets payment_tokens
     *
     * @return \Fiserv\Payments\Lib\CommerceHub\Model\ResponsePaymentToken[]|null
     */
    public function getPaymentTokens()
    {
        return $this->container['payment_tokens'];
    }

    /**
     * Sets payment_tokens
     *
     * @param \Fiserv\Payments\Lib\CommerceHub\Model\ResponsePaymentToken[]|null $payment_tokens payment_tokens
     *
     * @return self
     */
    public function setPaymentTokens($payment_tokens)
    {
        if (is_null($payment_tokens)) {
            throw new \InvalidArgumentException('non-nullable payment_tokens cannot be null');
        }
        $this->container['payment_tokens'] = $payment_tokens;

        return $this;
    }

    /**
     * Gets billing_address
     *
     * @return \Fiserv\Payments\Lib\CommerceHub\Model\BillingAddress|null
     */
    public function getBillingAddress()
    {
        return $this->container['billing_address'];
    }

    /**
     * Sets billing_address
     *
     * @param \Fiserv\Payments\Lib\CommerceHub\Model\BillingAddress|null $billing_address billing_address
     *
     * @return self
     */
    public function setBillingAddress($billing_address)
    {
        if (is_null($billing_address)) {
            throw new \InvalidArgumentException('non-nullable billing_address cannot be null');
        }
        $this->container['billing_address'] = $billing_address;

        return $this;
    }

    /**
     * Gets customer
     *
     * @return \Fiserv\Payments\Lib\CommerceHub\Model\Customer|null
     */
    public function getCustomer()
    {
        return $this->container['customer'];
    }

    /**
     * Sets customer
     *
     * @param \Fiserv\Payments\Lib\CommerceHub\Model\Customer|null $customer customer
     *
     * @return self
     */
    public function setCustomer($customer)
    {
        if (is_null($customer)) {
            throw new \InvalidArgumentException('non-nullable customer cannot be null');
        }
        $this->container['customer'] = $customer;

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


