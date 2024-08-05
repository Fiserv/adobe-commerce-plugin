<?php
/**
 * StoredCredentials
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
 * StoredCredentials Class Doc Comment
 *
 * @category Class
 * @description Used to initiate an initial or subsequent &lt;a href&#x3D;\&quot;../docs?path&#x3D;docs/Resources/Guides/Stored-Credentials.md\&quot;&gt;stored credentials&lt;/a&gt; transaction.
 * @package  Fiserv\Payments\Lib\CommerceHub
 * @author   OpenAPI Generator team
 * @link     https://openapi-generator.tech
 * @implements \ArrayAccess<string, mixed>
 */
class StoredCredentials implements ModelInterface, ArrayAccess, \JsonSerializable
{
    public const DISCRIMINATOR = null;

    /**
      * The original name of the model.
      *
      * @var string
      */
    protected static $openAPIModelName = 'StoredCredentials';

    /**
      * Array of property to type mappings. Used for (de)serialization
      *
      * @var string[]
      */
    protected static $openAPITypes = [
        'scheduled' => 'bool',
        'initiator' => 'string',
        'sequence' => 'string',
        'scheme_reference_transaction_id' => 'string',
        'origination_date' => '\DateTime',
        'account_age' => 'string',
        'count' => 'int',
        'last_updated' => 'string',
        'age' => 'string',
        'attempts' => 'int',
        'account_password_reset' => 'string',
        'six_month_transaction_count' => 'int',
        'twenty_four_hour_transaction_count' => 'int',
        'retry_attempts' => 'int',
        'network_transaction_reference' => 'string'
    ];

    /**
      * Array of property to format mappings. Used for (de)serialization
      *
      * @var string[]
      * @phpstan-var array<string, string|null>
      * @psalm-var array<string, string|null>
      */
    protected static $openAPIFormats = [
        'scheduled' => null,
        'initiator' => null,
        'sequence' => null,
        'scheme_reference_transaction_id' => null,
        'origination_date' => 'date',
        'account_age' => null,
        'count' => null,
        'last_updated' => null,
        'age' => null,
        'attempts' => null,
        'account_password_reset' => null,
        'six_month_transaction_count' => null,
        'twenty_four_hour_transaction_count' => null,
        'retry_attempts' => null,
        'network_transaction_reference' => null
    ];

    /**
      * Array of nullable properties. Used for (de)serialization
      *
      * @var boolean[]
      */
    protected static array $openAPINullables = [
        'scheduled' => false,
		'initiator' => false,
		'sequence' => false,
		'scheme_reference_transaction_id' => false,
		'origination_date' => false,
		'account_age' => false,
		'count' => false,
		'last_updated' => false,
		'age' => false,
		'attempts' => false,
		'account_password_reset' => false,
		'six_month_transaction_count' => false,
		'twenty_four_hour_transaction_count' => false,
		'retry_attempts' => false,
		'network_transaction_reference' => false
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
        'scheduled' => 'scheduled',
        'initiator' => 'initiator',
        'sequence' => 'sequence',
        'scheme_reference_transaction_id' => 'schemeReferenceTransactionId',
        'origination_date' => 'originationDate',
        'account_age' => 'accountAge',
        'count' => 'count',
        'last_updated' => 'lastUpdated',
        'age' => 'age',
        'attempts' => 'attempts',
        'account_password_reset' => 'accountPasswordReset',
        'six_month_transaction_count' => 'sixMonthTransactionCount',
        'twenty_four_hour_transaction_count' => 'twentyFourHourTransactionCount',
        'retry_attempts' => 'retryAttempts',
        'network_transaction_reference' => 'networkTransactionReference'
    ];

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @var string[]
     */
    protected static $setters = [
        'scheduled' => 'setScheduled',
        'initiator' => 'setInitiator',
        'sequence' => 'setSequence',
        'scheme_reference_transaction_id' => 'setSchemeReferenceTransactionId',
        'origination_date' => 'setOriginationDate',
        'account_age' => 'setAccountAge',
        'count' => 'setCount',
        'last_updated' => 'setLastUpdated',
        'age' => 'setAge',
        'attempts' => 'setAttempts',
        'account_password_reset' => 'setAccountPasswordReset',
        'six_month_transaction_count' => 'setSixMonthTransactionCount',
        'twenty_four_hour_transaction_count' => 'setTwentyFourHourTransactionCount',
        'retry_attempts' => 'setRetryAttempts',
        'network_transaction_reference' => 'setNetworkTransactionReference'
    ];

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @var string[]
     */
    protected static $getters = [
        'scheduled' => 'getScheduled',
        'initiator' => 'getInitiator',
        'sequence' => 'getSequence',
        'scheme_reference_transaction_id' => 'getSchemeReferenceTransactionId',
        'origination_date' => 'getOriginationDate',
        'account_age' => 'getAccountAge',
        'count' => 'getCount',
        'last_updated' => 'getLastUpdated',
        'age' => 'getAge',
        'attempts' => 'getAttempts',
        'account_password_reset' => 'getAccountPasswordReset',
        'six_month_transaction_count' => 'getSixMonthTransactionCount',
        'twenty_four_hour_transaction_count' => 'getTwentyFourHourTransactionCount',
        'retry_attempts' => 'getRetryAttempts',
        'network_transaction_reference' => 'getNetworkTransactionReference'
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
        $this->setIfExists('scheduled', $data ?? [], null);
        $this->setIfExists('initiator', $data ?? [], null);
        $this->setIfExists('sequence', $data ?? [], null);
        $this->setIfExists('scheme_reference_transaction_id', $data ?? [], null);
        $this->setIfExists('origination_date', $data ?? [], null);
        $this->setIfExists('account_age', $data ?? [], null);
        $this->setIfExists('count', $data ?? [], null);
        $this->setIfExists('last_updated', $data ?? [], null);
        $this->setIfExists('age', $data ?? [], null);
        $this->setIfExists('attempts', $data ?? [], null);
        $this->setIfExists('account_password_reset', $data ?? [], null);
        $this->setIfExists('six_month_transaction_count', $data ?? [], null);
        $this->setIfExists('twenty_four_hour_transaction_count', $data ?? [], null);
        $this->setIfExists('retry_attempts', $data ?? [], null);
        $this->setIfExists('network_transaction_reference', $data ?? [], null);
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

        if (!is_null($this->container['initiator']) && (mb_strlen($this->container['initiator']) > 11)) {
            $invalidProperties[] = "invalid value for 'initiator', the character length must be smaller than or equal to 11.";
        }

        if (!is_null($this->container['sequence']) && (mb_strlen($this->container['sequence']) > 10)) {
            $invalidProperties[] = "invalid value for 'sequence', the character length must be smaller than or equal to 10.";
        }

        if (!is_null($this->container['scheme_reference_transaction_id']) && (mb_strlen($this->container['scheme_reference_transaction_id']) > 256)) {
            $invalidProperties[] = "invalid value for 'scheme_reference_transaction_id', the character length must be smaller than or equal to 256.";
        }

        if (!is_null($this->container['origination_date']) && (mb_strlen($this->container['origination_date']) > 10)) {
            $invalidProperties[] = "invalid value for 'origination_date', the character length must be smaller than or equal to 10.";
        }

        if (!is_null($this->container['account_age']) && (mb_strlen($this->container['account_age']) > 32)) {
            $invalidProperties[] = "invalid value for 'account_age', the character length must be smaller than or equal to 32.";
        }

        if (!is_null($this->container['last_updated']) && (mb_strlen($this->container['last_updated']) > 32)) {
            $invalidProperties[] = "invalid value for 'last_updated', the character length must be smaller than or equal to 32.";
        }

        if (!is_null($this->container['age']) && (mb_strlen($this->container['age']) > 32)) {
            $invalidProperties[] = "invalid value for 'age', the character length must be smaller than or equal to 32.";
        }

        if (!is_null($this->container['account_password_reset']) && (mb_strlen($this->container['account_password_reset']) > 32)) {
            $invalidProperties[] = "invalid value for 'account_password_reset', the character length must be smaller than or equal to 32.";
        }

        if (!is_null($this->container['network_transaction_reference']) && (mb_strlen($this->container['network_transaction_reference']) > 64)) {
            $invalidProperties[] = "invalid value for 'network_transaction_reference', the character length must be smaller than or equal to 64.";
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
     * Gets scheduled
     *
     * @return bool|null
     */
    public function getScheduled()
    {
        return $this->container['scheduled'];
    }

    /**
     * Sets scheduled
     *
     * @param bool|null $scheduled Indicator if this is a scheduled transaction.
     *
     * @return self
     */
    public function setScheduled($scheduled)
    {

        if (is_null($scheduled)) {
            throw new \InvalidArgumentException('non-nullable scheduled cannot be null');
        }

        $this->container['scheduled'] = $scheduled;

        return $this;
    }

    /**
     * Gets initiator
     *
     * @return string|null
     */
    public function getInitiator()
    {
        return $this->container['initiator'];
    }

    /**
     * Sets initiator
     *
     * @param string|null $initiator Indicates whether it is a merchant-initiated or explicitly consented to by card holder.  Valid Values: * MERCHANT * CARD_HOLDER
     *
     * @return self
     */
    public function setInitiator($initiator)
    {
        if (!is_null($initiator) && (mb_strlen($initiator) > 11)) {
            throw new \InvalidArgumentException('invalid length for $initiator when calling StoredCredentials., must be smaller than or equal to 11.');
        }


        if (is_null($initiator)) {
            throw new \InvalidArgumentException('non-nullable initiator cannot be null');
        }

        $this->container['initiator'] = $initiator;

        return $this;
    }

    /**
     * Gets sequence
     *
     * @return string|null
     */
    public function getSequence()
    {
        return $this->container['sequence'];
    }

    /**
     * Sets sequence
     *
     * @param string|null $sequence Indicates if the transaction is first or subsequent.  Valid Values: * FIRST * SUBSEQUENT * SINGLE
     *
     * @return self
     */
    public function setSequence($sequence)
    {
        if (!is_null($sequence) && (mb_strlen($sequence) > 10)) {
            throw new \InvalidArgumentException('invalid length for $sequence when calling StoredCredentials., must be smaller than or equal to 10.');
        }


        if (is_null($sequence)) {
            throw new \InvalidArgumentException('non-nullable sequence cannot be null');
        }

        $this->container['sequence'] = $sequence;

        return $this;
    }

    /**
     * Gets scheme_reference_transaction_id
     *
     * @return string|null
     */
    public function getSchemeReferenceTransactionId()
    {
        return $this->container['scheme_reference_transaction_id'];
    }

    /**
     * Sets scheme_reference_transaction_id
     *
     * @param string|null $scheme_reference_transaction_id The transaction ID received from the issuer for the initial transaction. May be required if sequence is subsequent.
     *
     * @return self
     */
    public function setSchemeReferenceTransactionId($scheme_reference_transaction_id)
    {
        if (!is_null($scheme_reference_transaction_id) && (mb_strlen($scheme_reference_transaction_id) > 256)) {
            throw new \InvalidArgumentException('invalid length for $scheme_reference_transaction_id when calling StoredCredentials., must be smaller than or equal to 256.');
        }


        if (is_null($scheme_reference_transaction_id)) {
            throw new \InvalidArgumentException('non-nullable scheme_reference_transaction_id cannot be null');
        }

        $this->container['scheme_reference_transaction_id'] = $scheme_reference_transaction_id;

        return $this;
    }

    /**
     * Gets origination_date
     *
     * @return \DateTime|null
     */
    public function getOriginationDate()
    {
        return $this->container['origination_date'];
    }

    /**
     * Sets origination_date
     *
     * @param \DateTime|null $origination_date Date the customer account was created with merchant, in YYYY-MM-DD format.
     *
     * @return self
     */
    public function setOriginationDate($origination_date)
    {
        if (!is_null($origination_date) && (mb_strlen($origination_date) > 10)) {
            throw new \InvalidArgumentException('invalid length for $origination_date when calling StoredCredentials., must be smaller than or equal to 10.');
        }


        if (is_null($origination_date)) {
            throw new \InvalidArgumentException('non-nullable origination_date cannot be null');
        }

        $this->container['origination_date'] = $origination_date;

        return $this;
    }

    /**
     * Gets account_age
     *
     * @return string|null
     */
    public function getAccountAge()
    {
        return $this->container['account_age'];
    }

    /**
     * Sets account_age
     *
     * @param string|null $account_age Indicator on the age of customer account with merchant.  Valid Values:  * GUEST * NEW_ACCOUNT * LESS_THAN_30_DAYS * 30_60_DAYS * 60_90_DAYS * OVER_90_DAYS
     *
     * @return self
     */
    public function setAccountAge($account_age)
    {
        if (!is_null($account_age) && (mb_strlen($account_age) > 32)) {
            throw new \InvalidArgumentException('invalid length for $account_age when calling StoredCredentials., must be smaller than or equal to 32.');
        }


        if (is_null($account_age)) {
            throw new \InvalidArgumentException('non-nullable account_age cannot be null');
        }

        $this->container['account_age'] = $account_age;

        return $this;
    }

    /**
     * Gets count
     *
     * @return int|null
     */
    public function getCount()
    {
        return $this->container['count'];
    }

    /**
     * Sets count
     *
     * @param int|null $count Number of cards on file with this account.
     *
     * @return self
     */
    public function setCount($count)
    {

        if (is_null($count)) {
            throw new \InvalidArgumentException('non-nullable count cannot be null');
        }

        $this->container['count'] = $count;

        return $this;
    }

    /**
     * Gets last_updated
     *
     * @return string|null
     */
    public function getLastUpdated()
    {
        return $this->container['last_updated'];
    }

    /**
     * Sets last_updated
     *
     * @param string|null $last_updated Age of most recent card add/modify.  Valid Values:  * NEVER * NOW * LESS_THAN_30_DAYS * 30_60_DAYS * Over 60 DAYS
     *
     * @return self
     */
    public function setLastUpdated($last_updated)
    {
        if (!is_null($last_updated) && (mb_strlen($last_updated) > 32)) {
            throw new \InvalidArgumentException('invalid length for $last_updated when calling StoredCredentials., must be smaller than or equal to 32.');
        }


        if (is_null($last_updated)) {
            throw new \InvalidArgumentException('non-nullable last_updated cannot be null');
        }

        $this->container['last_updated'] = $last_updated;

        return $this;
    }

    /**
     * Gets age
     *
     * @return string|null
     */
    public function getAge()
    {
        return $this->container['age'];
    }

    /**
     * Sets age
     *
     * @param string|null $age Indicator on the age of this payment card on file with merchant.    Valid Values:  * GUEST * NEW_ACCOUNT * LESS_THAN_30_DAYS * 30_60_DAYS * 60_90_DAYS * OVER_90_DAYS
     *
     * @return self
     */
    public function setAge($age)
    {
        if (!is_null($age) && (mb_strlen($age) > 32)) {
            throw new \InvalidArgumentException('invalid length for $age when calling StoredCredentials., must be smaller than or equal to 32.');
        }


        if (is_null($age)) {
            throw new \InvalidArgumentException('non-nullable age cannot be null');
        }

        $this->container['age'] = $age;

        return $this;
    }

    /**
     * Gets attempts
     *
     * @return int|null
     */
    public function getAttempts()
    {
        return $this->container['attempts'];
    }

    /**
     * Sets attempts
     *
     * @param int|null $attempts Number of attempts to add a payment card in prior 24 hours.
     *
     * @return self
     */
    public function setAttempts($attempts)
    {

        if (is_null($attempts)) {
            throw new \InvalidArgumentException('non-nullable attempts cannot be null');
        }

        $this->container['attempts'] = $attempts;

        return $this;
    }

    /**
     * Gets account_password_reset
     *
     * @return string|null
     */
    public function getAccountPasswordReset()
    {
        return $this->container['account_password_reset'];
    }

    /**
     * Sets account_password_reset
     *
     * @param string|null $account_password_reset Indicator of the last time the account password was reset.  Valid Values:  * NEVER * NOW * LESS_THAN_30_DAYS * 30_60_DAYS * 60_90_DAYS * OVER_90_DAYS
     *
     * @return self
     */
    public function setAccountPasswordReset($account_password_reset)
    {
        if (!is_null($account_password_reset) && (mb_strlen($account_password_reset) > 32)) {
            throw new \InvalidArgumentException('invalid length for $account_password_reset when calling StoredCredentials., must be smaller than or equal to 32.');
        }


        if (is_null($account_password_reset)) {
            throw new \InvalidArgumentException('non-nullable account_password_reset cannot be null');
        }

        $this->container['account_password_reset'] = $account_password_reset;

        return $this;
    }

    /**
     * Gets six_month_transaction_count
     *
     * @return int|null
     */
    public function getSixMonthTransactionCount()
    {
        return $this->container['six_month_transaction_count'];
    }

    /**
     * Sets six_month_transaction_count
     *
     * @param int|null $six_month_transaction_count Number of transaction on this account in prior 6 months.
     *
     * @return self
     */
    public function setSixMonthTransactionCount($six_month_transaction_count)
    {

        if (is_null($six_month_transaction_count)) {
            throw new \InvalidArgumentException('non-nullable six_month_transaction_count cannot be null');
        }

        $this->container['six_month_transaction_count'] = $six_month_transaction_count;

        return $this;
    }

    /**
     * Gets twenty_four_hour_transaction_count
     *
     * @return int|null
     */
    public function getTwentyFourHourTransactionCount()
    {
        return $this->container['twenty_four_hour_transaction_count'];
    }

    /**
     * Sets twenty_four_hour_transaction_count
     *
     * @param int|null $twenty_four_hour_transaction_count Number of transaction on this account in prior 24 hours.
     *
     * @return self
     */
    public function setTwentyFourHourTransactionCount($twenty_four_hour_transaction_count)
    {

        if (is_null($twenty_four_hour_transaction_count)) {
            throw new \InvalidArgumentException('non-nullable twenty_four_hour_transaction_count cannot be null');
        }

        $this->container['twenty_four_hour_transaction_count'] = $twenty_four_hour_transaction_count;

        return $this;
    }

    /**
     * Gets retry_attempts
     *
     * @return int|null
     */
    public function getRetryAttempts()
    {
        return $this->container['retry_attempts'];
    }

    /**
     * Sets retry_attempts
     *
     * @param int|null $retry_attempts Number of retry attempt if the initial transaction was unsuccessful.
     *
     * @return self
     */
    public function setRetryAttempts($retry_attempts)
    {

        if (is_null($retry_attempts)) {
            throw new \InvalidArgumentException('non-nullable retry_attempts cannot be null');
        }

        $this->container['retry_attempts'] = $retry_attempts;

        return $this;
    }

    /**
     * Gets network_transaction_reference
     *
     * @return string|null
     */
    public function getNetworkTransactionReference()
    {
        return $this->container['network_transaction_reference'];
    }

    /**
     * Sets network_transaction_reference
     *
     * @param string|null $network_transaction_reference Allows linking of the transaction to the original or previous one in a subscription/card-on-file chain.
     *
     * @return self
     */
    public function setNetworkTransactionReference($network_transaction_reference)
    {
        if (!is_null($network_transaction_reference) && (mb_strlen($network_transaction_reference) > 64)) {
            throw new \InvalidArgumentException('invalid length for $network_transaction_reference when calling StoredCredentials., must be smaller than or equal to 64.');
        }


        if (is_null($network_transaction_reference)) {
            throw new \InvalidArgumentException('non-nullable network_transaction_reference cannot be null');
        }

        $this->container['network_transaction_reference'] = $network_transaction_reference;

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

