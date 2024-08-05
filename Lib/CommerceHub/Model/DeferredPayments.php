<?php
/**
 * DeferredPayments
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
 * DeferredPayments Class Doc Comment
 *
 * @category Class
 * @description Deferred bill payment transaction information.
 * @package  Fiserv\Payments\Lib\CommerceHub
 * @author   OpenAPI Generator team
 * @link     https://openapi-generator.tech
 * @implements \ArrayAccess<string, mixed>
 */
class DeferredPayments implements ModelInterface, ArrayAccess, \JsonSerializable
{
    public const DISCRIMINATOR = null;

    /**
      * The original name of the model.
      *
      * @var string
      */
    protected static $openAPIModelName = 'DeferredPayments';

    /**
      * Array of property to type mappings. Used for (de)serialization
      *
      * @var string[]
      */
    protected static $openAPITypes = [
        'number_of_payments' => 'string',
        'payment_plan' => 'string',
        'time_period' => 'string'
    ];

    /**
      * Array of property to format mappings. Used for (de)serialization
      *
      * @var string[]
      * @phpstan-var array<string, string|null>
      * @psalm-var array<string, string|null>
      */
    protected static $openAPIFormats = [
        'number_of_payments' => null,
        'payment_plan' => null,
        'time_period' => null
    ];

    /**
      * Array of nullable properties. Used for (de)serialization
      *
      * @var boolean[]
      */
    protected static array $openAPINullables = [
        'number_of_payments' => false,
		'payment_plan' => false,
		'time_period' => false
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
        'number_of_payments' => 'numberOfPayments',
        'payment_plan' => 'paymentPlan',
        'time_period' => 'timePeriod'
    ];

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @var string[]
     */
    protected static $setters = [
        'number_of_payments' => 'setNumberOfPayments',
        'payment_plan' => 'setPaymentPlan',
        'time_period' => 'setTimePeriod'
    ];

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @var string[]
     */
    protected static $getters = [
        'number_of_payments' => 'getNumberOfPayments',
        'payment_plan' => 'getPaymentPlan',
        'time_period' => 'getTimePeriod'
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
        $this->setIfExists('number_of_payments', $data ?? [], null);
        $this->setIfExists('payment_plan', $data ?? [], null);
        $this->setIfExists('time_period', $data ?? [], null);
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

        if (!is_null($this->container['number_of_payments']) && (mb_strlen($this->container['number_of_payments']) > 32)) {
            $invalidProperties[] = "invalid value for 'number_of_payments', the character length must be smaller than or equal to 32.";
        }

        if (!is_null($this->container['payment_plan']) && (mb_strlen($this->container['payment_plan']) > 11)) {
            $invalidProperties[] = "invalid value for 'payment_plan', the character length must be smaller than or equal to 11.";
        }

        if (!is_null($this->container['time_period']) && (mb_strlen($this->container['time_period']) > 64)) {
            $invalidProperties[] = "invalid value for 'time_period', the character length must be smaller than or equal to 64.";
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
     * Gets number_of_payments
     *
     * @return string|null
     */
    public function getNumberOfPayments()
    {
        return $this->container['number_of_payments'];
    }

    /**
     * Sets number_of_payments
     *
     * @param string|null $number_of_payments Number of payments for a sale transaction if the customer pays the total amount in multiple transactions.
     *
     * @return self
     */
    public function setNumberOfPayments($number_of_payments)
    {
        if (!is_null($number_of_payments) && (mb_strlen($number_of_payments) > 32)) {
            throw new \InvalidArgumentException('invalid length for $number_of_payments when calling DeferredPayments., must be smaller than or equal to 32.');
        }


        if (is_null($number_of_payments)) {
            throw new \InvalidArgumentException('non-nullable number_of_payments cannot be null');
        }

        $this->container['number_of_payments'] = $number_of_payments;

        return $this;
    }

    /**
     * Gets payment_plan
     *
     * @return string|null
     */
    public function getPaymentPlan()
    {
        return $this->container['payment_plan'];
    }

    /**
     * Sets payment_plan
     *
     * @param string|null $payment_plan This field details the type of the Deferred Payment Plan (DPP) for Mexican payments.  Valid Values: * NO_INTEREST – No Interest charges * INTEREST – Interest charges * PAY_LATER – Pay at a Later Date
     *
     * @return self
     */
    public function setPaymentPlan($payment_plan)
    {
        if (!is_null($payment_plan) && (mb_strlen($payment_plan) > 11)) {
            throw new \InvalidArgumentException('invalid length for $payment_plan when calling DeferredPayments., must be smaller than or equal to 11.');
        }


        if (is_null($payment_plan)) {
            throw new \InvalidArgumentException('non-nullable payment_plan cannot be null');
        }

        $this->container['payment_plan'] = $payment_plan;

        return $this;
    }

    /**
     * Gets time_period
     *
     * @return string|null
     */
    public function getTimePeriod()
    {
        return $this->container['time_period'];
    }

    /**
     * Sets time_period
     *
     * @param string|null $time_period Number of months for which the payment would not be enforced.
     *
     * @return self
     */
    public function setTimePeriod($time_period)
    {
        if (!is_null($time_period) && (mb_strlen($time_period) > 64)) {
            throw new \InvalidArgumentException('invalid length for $time_period when calling DeferredPayments., must be smaller than or equal to 64.');
        }


        if (is_null($time_period)) {
            throw new \InvalidArgumentException('non-nullable time_period cannot be null');
        }

        $this->container['time_period'] = $time_period;

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

