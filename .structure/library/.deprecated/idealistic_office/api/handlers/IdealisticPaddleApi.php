<?php

namespace idealistic_office\api\handlers;

use idealistic_office\api\IdealisticOfficeVariable;
use Paddle\SDK\Client;
use Paddle\SDK\Entities\Customer;
use Paddle\SDK\Entities\Subscription\SubscriptionItem;
use Paddle\SDK\Entities\Subscription\SubscriptionStatus;
use Paddle\SDK\Environment;
use Paddle\SDK\Options;
use Paddle\SDK\Resources\Customers\Operations\ListCustomers;
use Paddle\SDK\Resources\Subscriptions\Operations\ListSubscriptions;
use PhpAsyncSerializable;

class IdealisticPaddleApi implements PhpAsyncSerializable
{
    public const
        INCLUDED_CREDITS_KEY = 'included_credits',
        CONTEXT_LENGTH_KEY = 'context_length',
        HAS_LOGO_KEY = 'has_logo',
        CACHE_SECONDS = 300;

    public static function parseBoolean(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return $value != 0;
        }

        if (is_string($value)) {
            return match (strtolower(trim($value))) {
                'true', '1', 'yes' => true,
                'false', '0', 'no' => false,
                default => null
            };
        }
        return null;
    }

    public static function parseAmount(mixed $value): int
    {
        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
            return max(0, (int)$value);
        }

        if (is_string($value)
            && preg_match('/^\s*(\d+(?:\.\d+)?)\s*([km])\s*$/i', $value, $match) === 1) {
            return (int)round((float)$match[1] * (strtolower($match[2]) === 'm' ? 1_000_000 : 1_000));
        }
        return 0;
    }

    private static array $cache = [];

    private ?Client $client;

    public function __construct(bool $isSandbox = false)
    {
        $apiKey = get_keys_from_file("paddle_credentials", 1);

        if ($apiKey === null) {
            $this->client = null;
            return;
        }
        $environment = $isSandbox ? Environment::SANDBOX : Environment::PRODUCTION;
        $options = new Options($environment);
        $this->client = new Client($apiKey[0], $options);
    }

    private function getCache(int $cacheKey): mixed
    {
        $entry = self::$cache[$cacheKey] ?? null;

        if ($entry === null) {
            return null;
        }

        if ($entry[0] < time()) {
            unset(self::$cache[$cacheKey]);
            return null;
        }
        return $entry[1];
    }

    private function setCache(int $cacheKey, mixed $value): mixed
    {
        self::$cache[$cacheKey] = array(time() + self::CACHE_SECONDS, $value);
        return $value;
    }

    public function getCustomData(string $priceId, string $data): mixed
    {
        if ($this->client === null) {
            IdealisticOfficeError::simpleStore(
                "Paddle API client is not initialized to fetch custom data",
            );
            return 0;
        }
        $cacheKey = array_to_integer(
            array(
                __METHOD__, $priceId, $data
            )
        );
        $cache = $this->getCache($cacheKey);

        if ($cache !== null) {
            return $cache;
        }
        try {
            $price = $this->client->prices->get($priceId);

            if (isset($price->customData[$data])) {
                return $this->setCache($cacheKey, (int)$price->customData[$data]);
            }
        } catch (Throwable $e) {
        }
        return null;
    }

    public function getCustomerByEmail(string $email, int $secondsPassed = 0): ?Customer
    {
        if ($this->client === null) {
            IdealisticOfficeError::simpleStore(
                "Paddle API client is not initialized to fetch customer by email",
            );
            return null;
        }
        $cacheKey = array_to_integer(
            array(
                __METHOD__, $email
            )
        );
        $cache = $this->getCache($cacheKey);

        if ($cache !== null) {
            return $cache;
        }
        try {
            $operation = new ListCustomers(search: $email);
            $customerCollection = $this->client->customers->list($operation);

            foreach ($customerCollection as $customer) {
                if (strcasecmp($customer->email, $email) === 0) {
                    if ($secondsPassed > 0) {
                        $currentDate = IdealisticOfficeReader::getCurrentDate($customer->createdAt->getTimezone());
                        $createdDate = $customer->createdAt->format(IdealisticOfficeVariable::DEFAULT_DATE_FORMAT);
                        $secondsDifference = strtotime($currentDate) - strtotime($createdDate);

                        if ($secondsDifference <= $secondsPassed) {
                            return $this->setCache($cacheKey, $customer);
                        } else {
                            return null;
                        }
                    } else {
                        return $this->setCache($cacheKey, $customer);
                    }
                }
            }
        } catch (Throwable $e) {
            return null;
        }
        return null;
    }

    public function getCustomerById(string $customerId): ?Customer
    {
        if ($this->client === null) {
            IdealisticOfficeError::simpleStore(
                "Paddle API client is not initialized to fetch customer by ID",
            );
            return null;
        }
        $cacheKey = array_to_integer(
            array(
                __METHOD__, $customerId
            )
        );
        $cache = $this->getCache($cacheKey);

        if ($cache !== null) {
            return $cache;
        }
        try {
            $customer = $this->client->customers->get($customerId);
            return $this->setCache($cacheKey, $customer);
        } catch (Throwable $e) {
            return null;
        }
    }

    /** @return SubscriptionItem[] */
    public function getCustomerActiveItems(string $customerId): array
    {
        if ($this->client === null) {
            IdealisticOfficeError::simpleStore(
                "Paddle API client is not initialized to fetch customer active items",
            );
            return [];
        }
        $cacheKey = array_to_integer(
            array(
                __METHOD__, $customerId
            )
        );
        $cache = $this->getCache($cacheKey);

        if ($cache !== null) {
            return $cache;
        }
        $items = [];

        try {
            $operation = new ListSubscriptions(
                customerIds: [$customerId],
                statuses: [SubscriptionStatus::Active()]
            );
            $subscriptions = $this->client->subscriptions->list($operation);

            foreach ($subscriptions as $subscription) {
                foreach ($subscription->items as $item) {
                    $items[] = $item;
                }
            }
        } catch (Throwable $e) {
            return [];
        }
        return $this->setCache($cacheKey, $items);
    }

}