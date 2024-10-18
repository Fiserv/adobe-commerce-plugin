<?php
/**
 * EmvDataKey
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
 * EmvDataKey Class Doc Comment
 *
 * @category Class
 * @package  Fiserv\Payments\Lib\CommerceHub
 * @author   OpenAPI Generator team
 * @link     https://openapi-generator.tech
 * @implements \ArrayAccess<string, mixed>
 */
class EmvDataKey implements ModelInterface, ArrayAccess, \JsonSerializable
{
    public const DISCRIMINATOR = null;

    /**
      * The original name of the model.
      *
      * @var string
      */
    protected static $openAPIModelName = 'EmvDataKey';

    /**
      * Array of property to type mappings. Used for (de)serialization
      *
      * @var string[]
      */
    protected static $openAPITypes = [
        'expiry_date' => '\DateTime',
        'certificate_authority_hash_algorithm_indicator' => 'string',
        'certificate_authority_public_key_algorithm_indicator' => 'string',
        'application_identifier' => 'string',
        'public_key_index' => 'string',
        'public_key_modulus' => 'string',
        'public_key_exponent' => 'string',
        'public_key_checksum' => 'string'
    ];

    /**
      * Array of property to format mappings. Used for (de)serialization
      *
      * @var string[]
      * @phpstan-var array<string, string|null>
      * @psalm-var array<string, string|null>
      */
    protected static $openAPIFormats = [
        'expiry_date' => 'date',
        'certificate_authority_hash_algorithm_indicator' => null,
        'certificate_authority_public_key_algorithm_indicator' => null,
        'application_identifier' => null,
        'public_key_index' => null,
        'public_key_modulus' => null,
        'public_key_exponent' => null,
        'public_key_checksum' => null
    ];

    /**
      * Array of nullable properties. Used for (de)serialization
      *
      * @var boolean[]
      */
    protected static array $openAPINullables = [
        'expiry_date' => false,
        'certificate_authority_hash_algorithm_indicator' => false,
        'certificate_authority_public_key_algorithm_indicator' => false,
        'application_identifier' => false,
        'public_key_index' => false,
        'public_key_modulus' => false,
        'public_key_exponent' => false,
        'public_key_checksum' => false
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
        'expiry_date' => 'expiryDate',
        'certificate_authority_hash_algorithm_indicator' => 'certificateAuthorityHashAlgorithmIndicator',
        'certificate_authority_public_key_algorithm_indicator' => 'certificateAuthorityPublicKeyAlgorithmIndicator',
        'application_identifier' => 'applicationIdentifier',
        'public_key_index' => 'publicKeyIndex',
        'public_key_modulus' => 'publicKeyModulus',
        'public_key_exponent' => 'publicKeyExponent',
        'public_key_checksum' => 'publicKeyChecksum'
    ];

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @var string[]
     */
    protected static $setters = [
        'expiry_date' => 'setExpiryDate',
        'certificate_authority_hash_algorithm_indicator' => 'setCertificateAuthorityHashAlgorithmIndicator',
        'certificate_authority_public_key_algorithm_indicator' => 'setCertificateAuthorityPublicKeyAlgorithmIndicator',
        'application_identifier' => 'setApplicationIdentifier',
        'public_key_index' => 'setPublicKeyIndex',
        'public_key_modulus' => 'setPublicKeyModulus',
        'public_key_exponent' => 'setPublicKeyExponent',
        'public_key_checksum' => 'setPublicKeyChecksum'
    ];

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @var string[]
     */
    protected static $getters = [
        'expiry_date' => 'getExpiryDate',
        'certificate_authority_hash_algorithm_indicator' => 'getCertificateAuthorityHashAlgorithmIndicator',
        'certificate_authority_public_key_algorithm_indicator' => 'getCertificateAuthorityPublicKeyAlgorithmIndicator',
        'application_identifier' => 'getApplicationIdentifier',
        'public_key_index' => 'getPublicKeyIndex',
        'public_key_modulus' => 'getPublicKeyModulus',
        'public_key_exponent' => 'getPublicKeyExponent',
        'public_key_checksum' => 'getPublicKeyChecksum'
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
        $this->setIfExists('expiry_date', $data ?? [], null);
        $this->setIfExists('certificate_authority_hash_algorithm_indicator', $data ?? [], null);
        $this->setIfExists('certificate_authority_public_key_algorithm_indicator', $data ?? [], null);
        $this->setIfExists('application_identifier', $data ?? [], null);
        $this->setIfExists('public_key_index', $data ?? [], null);
        $this->setIfExists('public_key_modulus', $data ?? [], null);
        $this->setIfExists('public_key_exponent', $data ?? [], null);
        $this->setIfExists('public_key_checksum', $data ?? [], null);
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

        if (!is_null($this->container['certificate_authority_hash_algorithm_indicator']) && (mb_strlen($this->container['certificate_authority_hash_algorithm_indicator']) > 2)) {
            $invalidProperties[] = "invalid value for 'certificate_authority_hash_algorithm_indicator', the character length must be smaller than or equal to 2.";
        }

        if (!is_null($this->container['certificate_authority_public_key_algorithm_indicator']) && (mb_strlen($this->container['certificate_authority_public_key_algorithm_indicator']) > 2)) {
            $invalidProperties[] = "invalid value for 'certificate_authority_public_key_algorithm_indicator', the character length must be smaller than or equal to 2.";
        }

        if (!is_null($this->container['application_identifier']) && (mb_strlen($this->container['application_identifier']) > 10)) {
            $invalidProperties[] = "invalid value for 'application_identifier', the character length must be smaller than or equal to 10.";
        }

        if (!is_null($this->container['public_key_index']) && (mb_strlen($this->container['public_key_index']) > 2)) {
            $invalidProperties[] = "invalid value for 'public_key_index', the character length must be smaller than or equal to 2.";
        }

        if (!is_null($this->container['public_key_modulus']) && (mb_strlen($this->container['public_key_modulus']) > 496)) {
            $invalidProperties[] = "invalid value for 'public_key_modulus', the character length must be smaller than or equal to 496.";
        }

        if (!is_null($this->container['public_key_exponent']) && (mb_strlen($this->container['public_key_exponent']) > 6)) {
            $invalidProperties[] = "invalid value for 'public_key_exponent', the character length must be smaller than or equal to 6.";
        }

        if (!is_null($this->container['public_key_checksum']) && (mb_strlen($this->container['public_key_checksum']) > 40)) {
            $invalidProperties[] = "invalid value for 'public_key_checksum', the character length must be smaller than or equal to 40.";
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
     * Gets expiry_date
     *
     * @return \DateTime|null
     */
    public function getExpiryDate()
    {
        return $this->container['expiry_date'];
    }

    /**
     * Sets expiry_date
     *
     * @param \DateTime|null $expiry_date Specifies when the key expires in MMDDYYYY format.
     *
     * @return self
     */
    public function setExpiryDate($expiry_date)
    {
        if (is_null($expiry_date)) {
            throw new \InvalidArgumentException('non-nullable expiry_date cannot be null');
        }
        $this->container['expiry_date'] = $expiry_date;

        return $this;
    }

    /**
     * Gets certificate_authority_hash_algorithm_indicator
     *
     * @return string|null
     */
    public function getCertificateAuthorityHashAlgorithmIndicator()
    {
        return $this->container['certificate_authority_hash_algorithm_indicator'];
    }

    /**
     * Sets certificate_authority_hash_algorithm_indicator
     *
     * @param string|null $certificate_authority_hash_algorithm_indicator Identifies the hash algorithm used to produce the Hash Result in the digital signature scheme. At the present time only a value of (SHA-1) is supported.
     *
     * @return self
     */
    public function setCertificateAuthorityHashAlgorithmIndicator($certificate_authority_hash_algorithm_indicator)
    {
        if (is_null($certificate_authority_hash_algorithm_indicator)) {
            throw new \InvalidArgumentException('non-nullable certificate_authority_hash_algorithm_indicator cannot be null');
        }
        if ((mb_strlen($certificate_authority_hash_algorithm_indicator) > 2)) {
            throw new \InvalidArgumentException('invalid length for $certificate_authority_hash_algorithm_indicator when calling EmvDataKey., must be smaller than or equal to 2.');
        }

        $this->container['certificate_authority_hash_algorithm_indicator'] = $certificate_authority_hash_algorithm_indicator;

        return $this;
    }

    /**
     * Gets certificate_authority_public_key_algorithm_indicator
     *
     * @return string|null
     */
    public function getCertificateAuthorityPublicKeyAlgorithmIndicator()
    {
        return $this->container['certificate_authority_public_key_algorithm_indicator'];
    }

    /**
     * Sets certificate_authority_public_key_algorithm_indicator
     *
     * @param string|null $certificate_authority_public_key_algorithm_indicator Identifies the hash algorithm used to produce the Hash Result in the digital signature scheme. At the present time only a value of (SHA-1) is supported.
     *
     * @return self
     */
    public function setCertificateAuthorityPublicKeyAlgorithmIndicator($certificate_authority_public_key_algorithm_indicator)
    {
        if (is_null($certificate_authority_public_key_algorithm_indicator)) {
            throw new \InvalidArgumentException('non-nullable certificate_authority_public_key_algorithm_indicator cannot be null');
        }
        if ((mb_strlen($certificate_authority_public_key_algorithm_indicator) > 2)) {
            throw new \InvalidArgumentException('invalid length for $certificate_authority_public_key_algorithm_indicator when calling EmvDataKey., must be smaller than or equal to 2.');
        }

        $this->container['certificate_authority_public_key_algorithm_indicator'] = $certificate_authority_public_key_algorithm_indicator;

        return $this;
    }

    /**
     * Gets application_identifier
     *
     * @return string|null
     */
    public function getApplicationIdentifier()
    {
        return $this->container['application_identifier'];
    }

    /**
     * Sets application_identifier
     *
     * @param string|null $application_identifier Registered application identifier.
     *
     * @return self
     */
    public function setApplicationIdentifier($application_identifier)
    {
        if (is_null($application_identifier)) {
            throw new \InvalidArgumentException('non-nullable application_identifier cannot be null');
        }
        if ((mb_strlen($application_identifier) > 10)) {
            throw new \InvalidArgumentException('invalid length for $application_identifier when calling EmvDataKey., must be smaller than or equal to 10.');
        }

        $this->container['application_identifier'] = $application_identifier;

        return $this;
    }

    /**
     * Gets public_key_index
     *
     * @return string|null
     */
    public function getPublicKeyIndex()
    {
        return $this->container['public_key_index'];
    }

    /**
     * Sets public_key_index
     *
     * @param string|null $public_key_index Identifies the Certification Authority Public Key in conjunction with the applicationIdentifier.
     *
     * @return self
     */
    public function setPublicKeyIndex($public_key_index)
    {
        if (is_null($public_key_index)) {
            throw new \InvalidArgumentException('non-nullable public_key_index cannot be null');
        }
        if ((mb_strlen($public_key_index) > 2)) {
            throw new \InvalidArgumentException('invalid length for $public_key_index when calling EmvDataKey., must be smaller than or equal to 2.');
        }

        $this->container['public_key_index'] = $public_key_index;

        return $this;
    }

    /**
     * Gets public_key_modulus
     *
     * @return string|null
     */
    public function getPublicKeyModulus()
    {
        return $this->container['public_key_modulus'];
    }

    /**
     * Sets public_key_modulus
     *
     * @param string|null $public_key_modulus Value of the modulus part of the Certification Authority Public Key.
     *
     * @return self
     */
    public function setPublicKeyModulus($public_key_modulus)
    {
        if (is_null($public_key_modulus)) {
            throw new \InvalidArgumentException('non-nullable public_key_modulus cannot be null');
        }
        if ((mb_strlen($public_key_modulus) > 496)) {
            throw new \InvalidArgumentException('invalid length for $public_key_modulus when calling EmvDataKey., must be smaller than or equal to 496.');
        }

        $this->container['public_key_modulus'] = $public_key_modulus;

        return $this;
    }

    /**
     * Gets public_key_exponent
     *
     * @return string|null
     */
    public function getPublicKeyExponent()
    {
        return $this->container['public_key_exponent'];
    }

    /**
     * Sets public_key_exponent
     *
     * @param string|null $public_key_exponent Value of the exponent part of the Certification Authority Public Key, equal to 3 or 2^16+1.
     *
     * @return self
     */
    public function setPublicKeyExponent($public_key_exponent)
    {
        if (is_null($public_key_exponent)) {
            throw new \InvalidArgumentException('non-nullable public_key_exponent cannot be null');
        }
        if ((mb_strlen($public_key_exponent) > 6)) {
            throw new \InvalidArgumentException('invalid length for $public_key_exponent when calling EmvDataKey., must be smaller than or equal to 6.');
        }

        $this->container['public_key_exponent'] = $public_key_exponent;

        return $this;
    }

    /**
     * Gets public_key_checksum
     *
     * @return string|null
     */
    public function getPublicKeyChecksum()
    {
        return $this->container['public_key_checksum'];
    }

    /**
     * Sets public_key_checksum
     *
     * @param string|null $public_key_checksum A check value calculated on the concatenation of all parts of the certification Authority Public Key (ApplicationIdentifier, Certification Authority Public Key Index, Certification Authority Public Key Modulus, Certification Authority Public Key Exponent) using SHA-1.
     *
     * @return self
     */
    public function setPublicKeyChecksum($public_key_checksum)
    {
        if (is_null($public_key_checksum)) {
            throw new \InvalidArgumentException('non-nullable public_key_checksum cannot be null');
        }
        if ((mb_strlen($public_key_checksum) > 40)) {
            throw new \InvalidArgumentException('invalid length for $public_key_checksum when calling EmvDataKey., must be smaller than or equal to 40.');
        }

        $this->container['public_key_checksum'] = $public_key_checksum;

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


