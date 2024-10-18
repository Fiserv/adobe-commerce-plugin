<?php
/**
 * TransactionInquiryResponse
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
 * TransactionInquiryResponse Class Doc Comment
 *
 * @category Class
 * @package  Fiserv\Payments\Lib\CommerceHub
 * @author   OpenAPI Generator team
 * @link     https://openapi-generator.tech
 * @implements \ArrayAccess<string, mixed>
 */
class TransactionInquiryResponse implements ModelInterface, ArrayAccess, \JsonSerializable
{
    public const DISCRIMINATOR = null;

    /**
      * The original name of the model.
      *
      * @var string
      */
    protected static $openAPIModelName = 'TransactionInquiryResponse';

    /**
      * Array of property to type mappings. Used for (de)serialization
      *
      * @var string[]
      */
    protected static $openAPITypes = [
        'gateway_response' => '\Fiserv\Payments\Lib\CommerceHub\Model\GatewayResponse',
        'source' => '\Fiserv\Payments\Lib\CommerceHub\Model\Source',
        'payment_receipt' => '\Fiserv\Payments\Lib\CommerceHub\Model\PaymentReceipt',
        'billing_address' => '\Fiserv\Payments\Lib\CommerceHub\Model\BillingAddress',
        'shipping_address' => '\Fiserv\Payments\Lib\CommerceHub\Model\ShippingAddress',
        'transaction_details' => '\Fiserv\Payments\Lib\CommerceHub\Model\TransactionDetails',
        'transaction_interaction' => '\Fiserv\Payments\Lib\CommerceHub\Model\TransactionInteraction',
        'merchant_details' => '\Fiserv\Payments\Lib\CommerceHub\Model\MerchantDetails',
        'dynamic_descriptors' => '\Fiserv\Payments\Lib\CommerceHub\Model\DynamicDescriptors',
        'additional_data_common' => '\Fiserv\Payments\Lib\CommerceHub\Model\AdditionalDataCommon',
        'transaction_batch' => '\Fiserv\Payments\Lib\CommerceHub\Model\TransactionBatch',
        'network_details' => '\Fiserv\Payments\Lib\CommerceHub\Model\NetworkDetails',
        'card_details' => '\Fiserv\Payments\Lib\CommerceHub\Model\CardDetails',
        'payment_token' => '\Fiserv\Payments\Lib\CommerceHub\Model\ResponsePaymentToken',
        'payment_tokens' => '\Fiserv\Payments\Lib\CommerceHub\Model\ResponsePaymentToken[]',
        'stored_credentials' => '\Fiserv\Payments\Lib\CommerceHub\Model\StoredCredentials',
        'auth_optimization_details' => '\Fiserv\Payments\Lib\CommerceHub\Model\AuthOptimizationDetails',
        'error' => '\Fiserv\Payments\Lib\CommerceHub\Model\Error[]'
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
        'source' => null,
        'payment_receipt' => null,
        'billing_address' => null,
        'shipping_address' => null,
        'transaction_details' => null,
        'transaction_interaction' => null,
        'merchant_details' => null,
        'dynamic_descriptors' => null,
        'additional_data_common' => null,
        'transaction_batch' => null,
        'network_details' => null,
        'card_details' => null,
        'payment_token' => null,
        'payment_tokens' => null,
        'stored_credentials' => null,
        'auth_optimization_details' => null,
        'error' => null
    ];

    /**
      * Array of nullable properties. Used for (de)serialization
      *
      * @var boolean[]
      */
    protected static array $openAPINullables = [
        'gateway_response' => false,
        'source' => false,
        'payment_receipt' => false,
        'billing_address' => false,
        'shipping_address' => false,
        'transaction_details' => false,
        'transaction_interaction' => false,
        'merchant_details' => false,
        'dynamic_descriptors' => false,
        'additional_data_common' => false,
        'transaction_batch' => false,
        'network_details' => false,
        'card_details' => false,
        'payment_token' => false,
        'payment_tokens' => false,
        'stored_credentials' => false,
        'auth_optimization_details' => false,
        'error' => false
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
        'source' => 'source',
        'payment_receipt' => 'paymentReceipt',
        'billing_address' => 'billingAddress',
        'shipping_address' => 'shippingAddress',
        'transaction_details' => 'transactionDetails',
        'transaction_interaction' => 'transactionInteraction',
        'merchant_details' => 'merchantDetails',
        'dynamic_descriptors' => 'dynamicDescriptors',
        'additional_data_common' => 'additionalDataCommon',
        'transaction_batch' => 'transactionBatch',
        'network_details' => 'networkDetails',
        'card_details' => 'cardDetails',
        'payment_token' => 'paymentToken',
        'payment_tokens' => 'paymentTokens',
        'stored_credentials' => 'storedCredentials',
        'auth_optimization_details' => 'authOptimizationDetails',
        'error' => 'error'
    ];

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @var string[]
     */
    protected static $setters = [
        'gateway_response' => 'setGatewayResponse',
        'source' => 'setSource',
        'payment_receipt' => 'setPaymentReceipt',
        'billing_address' => 'setBillingAddress',
        'shipping_address' => 'setShippingAddress',
        'transaction_details' => 'setTransactionDetails',
        'transaction_interaction' => 'setTransactionInteraction',
        'merchant_details' => 'setMerchantDetails',
        'dynamic_descriptors' => 'setDynamicDescriptors',
        'additional_data_common' => 'setAdditionalDataCommon',
        'transaction_batch' => 'setTransactionBatch',
        'network_details' => 'setNetworkDetails',
        'card_details' => 'setCardDetails',
        'payment_token' => 'setPaymentToken',
        'payment_tokens' => 'setPaymentTokens',
        'stored_credentials' => 'setStoredCredentials',
        'auth_optimization_details' => 'setAuthOptimizationDetails',
        'error' => 'setError'
    ];

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @var string[]
     */
    protected static $getters = [
        'gateway_response' => 'getGatewayResponse',
        'source' => 'getSource',
        'payment_receipt' => 'getPaymentReceipt',
        'billing_address' => 'getBillingAddress',
        'shipping_address' => 'getShippingAddress',
        'transaction_details' => 'getTransactionDetails',
        'transaction_interaction' => 'getTransactionInteraction',
        'merchant_details' => 'getMerchantDetails',
        'dynamic_descriptors' => 'getDynamicDescriptors',
        'additional_data_common' => 'getAdditionalDataCommon',
        'transaction_batch' => 'getTransactionBatch',
        'network_details' => 'getNetworkDetails',
        'card_details' => 'getCardDetails',
        'payment_token' => 'getPaymentToken',
        'payment_tokens' => 'getPaymentTokens',
        'stored_credentials' => 'getStoredCredentials',
        'auth_optimization_details' => 'getAuthOptimizationDetails',
        'error' => 'getError'
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
        $this->setIfExists('source', $data ?? [], null);
        $this->setIfExists('payment_receipt', $data ?? [], null);
        $this->setIfExists('billing_address', $data ?? [], null);
        $this->setIfExists('shipping_address', $data ?? [], null);
        $this->setIfExists('transaction_details', $data ?? [], null);
        $this->setIfExists('transaction_interaction', $data ?? [], null);
        $this->setIfExists('merchant_details', $data ?? [], null);
        $this->setIfExists('dynamic_descriptors', $data ?? [], null);
        $this->setIfExists('additional_data_common', $data ?? [], null);
        $this->setIfExists('transaction_batch', $data ?? [], null);
        $this->setIfExists('network_details', $data ?? [], null);
        $this->setIfExists('card_details', $data ?? [], null);
        $this->setIfExists('payment_token', $data ?? [], null);
        $this->setIfExists('payment_tokens', $data ?? [], null);
        $this->setIfExists('stored_credentials', $data ?? [], null);
        $this->setIfExists('auth_optimization_details', $data ?? [], null);
        $this->setIfExists('error', $data ?? [], null);
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
     * Gets payment_receipt
     *
     * @return \Fiserv\Payments\Lib\CommerceHub\Model\PaymentReceipt|null
     */
    public function getPaymentReceipt()
    {
        return $this->container['payment_receipt'];
    }

    /**
     * Sets payment_receipt
     *
     * @param \Fiserv\Payments\Lib\CommerceHub\Model\PaymentReceipt|null $payment_receipt payment_receipt
     *
     * @return self
     */
    public function setPaymentReceipt($payment_receipt)
    {
        if (is_null($payment_receipt)) {
            throw new \InvalidArgumentException('non-nullable payment_receipt cannot be null');
        }
        $this->container['payment_receipt'] = $payment_receipt;

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
     * Gets shipping_address
     *
     * @return \Fiserv\Payments\Lib\CommerceHub\Model\ShippingAddress|null
     */
    public function getShippingAddress()
    {
        return $this->container['shipping_address'];
    }

    /**
     * Sets shipping_address
     *
     * @param \Fiserv\Payments\Lib\CommerceHub\Model\ShippingAddress|null $shipping_address shipping_address
     *
     * @return self
     */
    public function setShippingAddress($shipping_address)
    {
        if (is_null($shipping_address)) {
            throw new \InvalidArgumentException('non-nullable shipping_address cannot be null');
        }
        $this->container['shipping_address'] = $shipping_address;

        return $this;
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
     * Gets transaction_interaction
     *
     * @return \Fiserv\Payments\Lib\CommerceHub\Model\TransactionInteraction|null
     */
    public function getTransactionInteraction()
    {
        return $this->container['transaction_interaction'];
    }

    /**
     * Sets transaction_interaction
     *
     * @param \Fiserv\Payments\Lib\CommerceHub\Model\TransactionInteraction|null $transaction_interaction transaction_interaction
     *
     * @return self
     */
    public function setTransactionInteraction($transaction_interaction)
    {
        if (is_null($transaction_interaction)) {
            throw new \InvalidArgumentException('non-nullable transaction_interaction cannot be null');
        }
        $this->container['transaction_interaction'] = $transaction_interaction;

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
     * Gets dynamic_descriptors
     *
     * @return \Fiserv\Payments\Lib\CommerceHub\Model\DynamicDescriptors|null
     */
    public function getDynamicDescriptors()
    {
        return $this->container['dynamic_descriptors'];
    }

    /**
     * Sets dynamic_descriptors
     *
     * @param \Fiserv\Payments\Lib\CommerceHub\Model\DynamicDescriptors|null $dynamic_descriptors dynamic_descriptors
     *
     * @return self
     */
    public function setDynamicDescriptors($dynamic_descriptors)
    {
        if (is_null($dynamic_descriptors)) {
            throw new \InvalidArgumentException('non-nullable dynamic_descriptors cannot be null');
        }
        $this->container['dynamic_descriptors'] = $dynamic_descriptors;

        return $this;
    }

    /**
     * Gets additional_data_common
     *
     * @return \Fiserv\Payments\Lib\CommerceHub\Model\AdditionalDataCommon|null
     */
    public function getAdditionalDataCommon()
    {
        return $this->container['additional_data_common'];
    }

    /**
     * Sets additional_data_common
     *
     * @param \Fiserv\Payments\Lib\CommerceHub\Model\AdditionalDataCommon|null $additional_data_common additional_data_common
     *
     * @return self
     */
    public function setAdditionalDataCommon($additional_data_common)
    {
        if (is_null($additional_data_common)) {
            throw new \InvalidArgumentException('non-nullable additional_data_common cannot be null');
        }
        $this->container['additional_data_common'] = $additional_data_common;

        return $this;
    }

    /**
     * Gets transaction_batch
     *
     * @return \Fiserv\Payments\Lib\CommerceHub\Model\TransactionBatch|null
     */
    public function getTransactionBatch()
    {
        return $this->container['transaction_batch'];
    }

    /**
     * Sets transaction_batch
     *
     * @param \Fiserv\Payments\Lib\CommerceHub\Model\TransactionBatch|null $transaction_batch transaction_batch
     *
     * @return self
     */
    public function setTransactionBatch($transaction_batch)
    {
        if (is_null($transaction_batch)) {
            throw new \InvalidArgumentException('non-nullable transaction_batch cannot be null');
        }
        $this->container['transaction_batch'] = $transaction_batch;

        return $this;
    }

    /**
     * Gets network_details
     *
     * @return \Fiserv\Payments\Lib\CommerceHub\Model\NetworkDetails|null
     */
    public function getNetworkDetails()
    {
        return $this->container['network_details'];
    }

    /**
     * Sets network_details
     *
     * @param \Fiserv\Payments\Lib\CommerceHub\Model\NetworkDetails|null $network_details network_details
     *
     * @return self
     */
    public function setNetworkDetails($network_details)
    {
        if (is_null($network_details)) {
            throw new \InvalidArgumentException('non-nullable network_details cannot be null');
        }
        $this->container['network_details'] = $network_details;

        return $this;
    }

    /**
     * Gets card_details
     *
     * @return \Fiserv\Payments\Lib\CommerceHub\Model\CardDetails|null
     */
    public function getCardDetails()
    {
        return $this->container['card_details'];
    }

    /**
     * Sets card_details
     *
     * @param \Fiserv\Payments\Lib\CommerceHub\Model\CardDetails|null $card_details card_details
     *
     * @return self
     */
    public function setCardDetails($card_details)
    {
        if (is_null($card_details)) {
            throw new \InvalidArgumentException('non-nullable card_details cannot be null');
        }
        $this->container['card_details'] = $card_details;

        return $this;
    }

    /**
     * Gets payment_token
     *
     * @return \Fiserv\Payments\Lib\CommerceHub\Model\ResponsePaymentToken|null
     */
    public function getPaymentToken()
    {
        return $this->container['payment_token'];
    }

    /**
     * Sets payment_token
     *
     * @param \Fiserv\Payments\Lib\CommerceHub\Model\ResponsePaymentToken|null $payment_token payment_token
     *
     * @return self
     */
    public function setPaymentToken($payment_token)
    {
        if (is_null($payment_token)) {
            throw new \InvalidArgumentException('non-nullable payment_token cannot be null');
        }
        $this->container['payment_token'] = $payment_token;

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
     * Gets stored_credentials
     *
     * @return \Fiserv\Payments\Lib\CommerceHub\Model\StoredCredentials|null
     */
    public function getStoredCredentials()
    {
        return $this->container['stored_credentials'];
    }

    /**
     * Sets stored_credentials
     *
     * @param \Fiserv\Payments\Lib\CommerceHub\Model\StoredCredentials|null $stored_credentials stored_credentials
     *
     * @return self
     */
    public function setStoredCredentials($stored_credentials)
    {
        if (is_null($stored_credentials)) {
            throw new \InvalidArgumentException('non-nullable stored_credentials cannot be null');
        }
        $this->container['stored_credentials'] = $stored_credentials;

        return $this;
    }

    /**
     * Gets auth_optimization_details
     *
     * @return \Fiserv\Payments\Lib\CommerceHub\Model\AuthOptimizationDetails|null
     */
    public function getAuthOptimizationDetails()
    {
        return $this->container['auth_optimization_details'];
    }

    /**
     * Sets auth_optimization_details
     *
     * @param \Fiserv\Payments\Lib\CommerceHub\Model\AuthOptimizationDetails|null $auth_optimization_details auth_optimization_details
     *
     * @return self
     */
    public function setAuthOptimizationDetails($auth_optimization_details)
    {
        if (is_null($auth_optimization_details)) {
            throw new \InvalidArgumentException('non-nullable auth_optimization_details cannot be null');
        }
        $this->container['auth_optimization_details'] = $auth_optimization_details;

        return $this;
    }

    /**
     * Gets error
     *
     * @return \Fiserv\Payments\Lib\CommerceHub\Model\Error[]|null
     */
    public function getError()
    {
        return $this->container['error'];
    }

    /**
     * Sets error
     *
     * @param \Fiserv\Payments\Lib\CommerceHub\Model\Error[]|null $error error
     *
     * @return self
     */
    public function setError($error)
    {
        if (is_null($error)) {
            throw new \InvalidArgumentException('non-nullable error cannot be null');
        }
        $this->container['error'] = $error;

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


