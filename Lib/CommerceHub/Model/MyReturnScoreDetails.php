<?php
/**
 * MyReturnScoreDetails
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
 * MyReturnScoreDetails Class Doc Comment
 *
 * @category Class
 * @description &lt;a href&#x3D;\&quot;..docs/?path&#x3D;docs/Resources/API-Documents/DaaS/Returns-Optimizer-Service.md#tab-myreturnscoredetails&gt;My Return Score Details&lt;/a&gt;
 * @package  Fiserv\Payments\Lib\CommerceHub
 * @author   OpenAPI Generator team
 * @link     https://openapi-generator.tech
 * @implements \ArrayAccess<string, mixed>
 */
class MyReturnScoreDetails implements ModelInterface, ArrayAccess, \JsonSerializable
{
    public const DISCRIMINATOR = null;

    /**
      * The original name of the model.
      *
      * @var string
      */
    protected static $openAPIModelName = 'MyReturnScoreDetails';

    /**
      * Array of property to type mappings. Used for (de)serialization
      *
      * @var string[]
      */
    protected static $openAPITypes = [
        'average_return_value' => 'float',
        'average_order_value' => 'float',
        'percent_sales_returned' => 'float',
        'score' => 'int',
        'return_probability_profile' => 'string'
    ];

    /**
      * Array of property to format mappings. Used for (de)serialization
      *
      * @var string[]
      * @phpstan-var array<string, string|null>
      * @psalm-var array<string, string|null>
      */
    protected static $openAPIFormats = [
        'average_return_value' => null,
        'average_order_value' => null,
        'percent_sales_returned' => null,
        'score' => null,
        'return_probability_profile' => null
    ];

    /**
      * Array of nullable properties. Used for (de)serialization
      *
      * @var boolean[]
      */
    protected static array $openAPINullables = [
        'average_return_value' => false,
        'average_order_value' => false,
        'percent_sales_returned' => false,
        'score' => false,
        'return_probability_profile' => false
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
        'average_return_value' => 'averageReturnValue',
        'average_order_value' => 'averageOrderValue',
        'percent_sales_returned' => 'percentSalesReturned',
        'score' => 'score',
        'return_probability_profile' => 'returnProbabilityProfile'
    ];

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @var string[]
     */
    protected static $setters = [
        'average_return_value' => 'setAverageReturnValue',
        'average_order_value' => 'setAverageOrderValue',
        'percent_sales_returned' => 'setPercentSalesReturned',
        'score' => 'setScore',
        'return_probability_profile' => 'setReturnProbabilityProfile'
    ];

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @var string[]
     */
    protected static $getters = [
        'average_return_value' => 'getAverageReturnValue',
        'average_order_value' => 'getAverageOrderValue',
        'percent_sales_returned' => 'getPercentSalesReturned',
        'score' => 'getScore',
        'return_probability_profile' => 'getReturnProbabilityProfile'
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
        $this->setIfExists('average_return_value', $data ?? [], null);
        $this->setIfExists('average_order_value', $data ?? [], null);
        $this->setIfExists('percent_sales_returned', $data ?? [], null);
        $this->setIfExists('score', $data ?? [], null);
        $this->setIfExists('return_probability_profile', $data ?? [], null);
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

        if (!is_null($this->container['percent_sales_returned']) && ($this->container['percent_sales_returned'] > 1)) {
            $invalidProperties[] = "invalid value for 'percent_sales_returned', must be smaller than or equal to 1.";
        }

        if (!is_null($this->container['percent_sales_returned']) && ($this->container['percent_sales_returned'] < 0)) {
            $invalidProperties[] = "invalid value for 'percent_sales_returned', must be bigger than or equal to 0.";
        }

        if (!is_null($this->container['score']) && ($this->container['score'] > 100)) {
            $invalidProperties[] = "invalid value for 'score', must be smaller than or equal to 100.";
        }

        if (!is_null($this->container['score']) && ($this->container['score'] < 0)) {
            $invalidProperties[] = "invalid value for 'score', must be bigger than or equal to 0.";
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
     * Gets average_return_value
     *
     * @return float|null
     */
    public function getAverageReturnValue()
    {
        return $this->container['average_return_value'];
    }

    /**
     * Sets average_return_value
     *
     * @param float|null $average_return_value Average amount value returned (ARV)
     *
     * @return self
     */
    public function setAverageReturnValue($average_return_value)
    {
        if (is_null($average_return_value)) {
            throw new \InvalidArgumentException('non-nullable average_return_value cannot be null');
        }
        $this->container['average_return_value'] = $average_return_value;

        return $this;
    }

    /**
     * Gets average_order_value
     *
     * @return float|null
     */
    public function getAverageOrderValue()
    {
        return $this->container['average_order_value'];
    }

    /**
     * Sets average_order_value
     *
     * @param float|null $average_order_value Average amount value spent (AOV)
     *
     * @return self
     */
    public function setAverageOrderValue($average_order_value)
    {
        if (is_null($average_order_value)) {
            throw new \InvalidArgumentException('non-nullable average_order_value cannot be null');
        }
        $this->container['average_order_value'] = $average_order_value;

        return $this;
    }

    /**
     * Gets percent_sales_returned
     *
     * @return float|null
     */
    public function getPercentSalesReturned()
    {
        return $this->container['percent_sales_returned'];
    }

    /**
     * Sets percent_sales_returned
     *
     * @param float|null $percent_sales_returned % of sales returned (by Amount)
     *
     * @return self
     */
    public function setPercentSalesReturned($percent_sales_returned)
    {
        if (is_null($percent_sales_returned)) {
            throw new \InvalidArgumentException('non-nullable percent_sales_returned cannot be null');
        }

        if (($percent_sales_returned > 1)) {
            throw new \InvalidArgumentException('invalid value for $percent_sales_returned when calling MyReturnScoreDetails., must be smaller than or equal to 1.');
        }
        if (($percent_sales_returned < 0)) {
            throw new \InvalidArgumentException('invalid value for $percent_sales_returned when calling MyReturnScoreDetails., must be bigger than or equal to 0.');
        }

        $this->container['percent_sales_returned'] = $percent_sales_returned;

        return $this;
    }

    /**
     * Gets score
     *
     * @return int|null
     */
    public function getScore()
    {
        return $this->container['score'];
    }

    /**
     * Sets score
     *
     * @param int|null $score Normalized score of a return probability. 0 indicating a low returner and 100 indicating a high returner.
     *
     * @return self
     */
    public function setScore($score)
    {
        if (is_null($score)) {
            throw new \InvalidArgumentException('non-nullable score cannot be null');
        }

        if (($score > 100)) {
            throw new \InvalidArgumentException('invalid value for $score when calling MyReturnScoreDetails., must be smaller than or equal to 100.');
        }
        if (($score < 0)) {
            throw new \InvalidArgumentException('invalid value for $score when calling MyReturnScoreDetails., must be bigger than or equal to 0.');
        }

        $this->container['score'] = $score;

        return $this;
    }

    /**
     * Gets return_probability_profile
     *
     * @return string|null
     */
    public function getReturnProbabilityProfile()
    {
        return $this->container['return_probability_profile'];
    }

    /**
     * Sets return_probability_profile
     *
     * @param string|null $return_probability_profile Bucketed <a href=\"..docs/?path=docs/Resources/API-Documents/DaaS/Returns-Optimizer-Service.md#response-variables\">return probability</a> profile
     *
     * @return self
     */
    public function setReturnProbabilityProfile($return_probability_profile)
    {
        if (is_null($return_probability_profile)) {
            throw new \InvalidArgumentException('non-nullable return_probability_profile cannot be null');
        }
        $this->container['return_probability_profile'] = $return_probability_profile;

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


