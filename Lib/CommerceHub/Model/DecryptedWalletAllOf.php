<?php
/**
 * DecryptedWalletAllOf
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
 * DecryptedWalletAllOf Class Doc Comment
 *
 * @category Class
 * @package  Fiserv\Payments\Lib\CommerceHub
 * @author   OpenAPI Generator team
 * @link     https://openapi-generator.tech
 * @implements \ArrayAccess<string, mixed>
 */
class DecryptedWalletAllOf implements ModelInterface, ArrayAccess, \JsonSerializable
{
    public const DISCRIMINATOR = null;

    /**
      * The original name of the model.
      *
      * @var string
      */
    protected static $openAPIModelName = 'DecryptedWallet_allOf';

    /**
      * Array of property to type mappings. Used for (de)serialization
      *
      * @var string[]
      */
    protected static $openAPITypes = [
        'card' => '\Fiserv\Payments\Lib\CommerceHub\Model\Card',
        'cavv' => 'string',
        'xid' => 'string',
        'wallet_type' => 'string'
    ];

    /**
      * Array of property to format mappings. Used for (de)serialization
      *
      * @var string[]
      * @phpstan-var array<string, string|null>
      * @psalm-var array<string, string|null>
      */
    protected static $openAPIFormats = [
        'card' => null,
        'cavv' => null,
        'xid' => null,
        'wallet_type' => null
    ];

    /**
      * Array of nullable properties. Used for (de)serialization
      *
      * @var boolean[]
      */
    protected static array $openAPINullables = [
        'card' => false,
		'cavv' => false,
		'xid' => false,
		'wallet_type' => false
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
        'card' => 'card',
        'cavv' => 'cavv',
        'xid' => 'xid',
        'wallet_type' => 'walletType'
    ];

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @var string[]
     */
    protected static $setters = [
        'card' => 'setCard',
        'cavv' => 'setCavv',
        'xid' => 'setXid',
        'wallet_type' => 'setWalletType'
    ];

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @var string[]
     */
    protected static $getters = [
        'card' => 'getCard',
        'cavv' => 'getCavv',
        'xid' => 'getXid',
        'wallet_type' => 'getWalletType'
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
        $this->setIfExists('card', $data ?? [], null);
        $this->setIfExists('cavv', $data ?? [], null);
        $this->setIfExists('xid', $data ?? [], null);
        $this->setIfExists('wallet_type', $data ?? [], null);
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

        if (!is_null($this->container['cavv']) && (mb_strlen($this->container['cavv']) > 40)) {
            $invalidProperties[] = "invalid value for 'cavv', the character length must be smaller than or equal to 40.";
        }

        if (!is_null($this->container['xid']) && (mb_strlen($this->container['xid']) > 40)) {
            $invalidProperties[] = "invalid value for 'xid', the character length must be smaller than or equal to 40.";
        }

        if (!is_null($this->container['wallet_type']) && (mb_strlen($this->container['wallet_type']) > 256)) {
            $invalidProperties[] = "invalid value for 'wallet_type', the character length must be smaller than or equal to 256.";
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
     * Gets card
     *
     * @return \Fiserv\Payments\Lib\CommerceHub\Model\Card|null
     */
    public function getCard()
    {
        return $this->container['card'];
    }

    /**
     * Sets card
     *
     * @param \Fiserv\Payments\Lib\CommerceHub\Model\Card|null $card card
     *
     * @return self
     */
    public function setCard($card)
    {

        if (is_null($card)) {
            throw new \InvalidArgumentException('non-nullable card cannot be null');
        }

        $this->container['card'] = $card;

        return $this;
    }

    /**
     * Gets cavv
     *
     * @return string|null
     */
    public function getCavv()
    {
        return $this->container['cavv'];
    }

    /**
     * Sets cavv
     *
     * @param string|null $cavv Cryptogram.
     *
     * @return self
     */
    public function setCavv($cavv)
    {
        if (!is_null($cavv) && (mb_strlen($cavv) > 40)) {
            throw new \InvalidArgumentException('invalid length for $cavv when calling DecryptedWalletAllOf., must be smaller than or equal to 40.');
        }


        if (is_null($cavv)) {
            throw new \InvalidArgumentException('non-nullable cavv cannot be null');
        }

        $this->container['cavv'] = $cavv;

        return $this;
    }

    /**
     * Gets xid
     *
     * @return string|null
     */
    public function getXid()
    {
        return $this->container['xid'];
    }

    /**
     * Sets xid
     *
     * @param string|null $xid Cryptogram.
     *
     * @return self
     */
    public function setXid($xid)
    {
        if (!is_null($xid) && (mb_strlen($xid) > 40)) {
            throw new \InvalidArgumentException('invalid length for $xid when calling DecryptedWalletAllOf., must be smaller than or equal to 40.');
        }


        if (is_null($xid)) {
            throw new \InvalidArgumentException('non-nullable xid cannot be null');
        }

        $this->container['xid'] = $xid;

        return $this;
    }

    /**
     * Gets wallet_type
     *
     * @return string|null
     */
    public function getWalletType()
    {
        return $this->container['wallet_type'];
    }

    /**
     * Sets wallet_type
     *
     * @param string|null $wallet_type Identifies the wallet as APPLE_PAY, GOOGLE_PAY, or SAMSUNG_PAY.
     *
     * @return self
     */
    public function setWalletType($wallet_type)
    {
        if (!is_null($wallet_type) && (mb_strlen($wallet_type) > 256)) {
            throw new \InvalidArgumentException('invalid length for $wallet_type when calling DecryptedWalletAllOf., must be smaller than or equal to 256.');
        }


        if (is_null($wallet_type)) {
            throw new \InvalidArgumentException('non-nullable wallet_type cannot be null');
        }

        $this->container['wallet_type'] = $wallet_type;

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


