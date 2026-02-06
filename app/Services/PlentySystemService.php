<?php

namespace App\Services;

use App\Exceptions\PlentySystemException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PlentySystemService
{
    private string $baseUrl;

    private string $username;

    private string $password;

    private int $timeout;

    private int $maxRetries = 3;

    private int $retryDelayMs = 1000;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('plentysystem.base_url'), '/');
        $this->username = (string) config('plentysystem.username');
        $this->password = (string) config('plentysystem.password');
        $this->timeout = (int) config('plentysystem.timeout', 30);
    }

    /**
     * Get the access token, caching it for 23 hours (token valid for 24 hours).
     */
    public function getAccessToken(): string
    {
        return Cache::remember('plentysystem_access_token', 82800, function () {
            try {
                $response = Http::timeout($this->timeout)
                    ->post("{$this->baseUrl}/rest/login", [
                        'username' => $this->username,
                        'password' => $this->password,
                    ]);

                if ($response->status() === 401) {
                    throw PlentySystemException::authentication(
                        'Invalid credentials for PlentySystem API'
                    );
                }

                if (! $response->successful()) {
                    throw PlentySystemException::serverError(
                        'Failed to authenticate: '.$response->body()
                    );
                }

                return $response->json('accessToken');
            } catch (ConnectionException $e) {
                $this->handleConnectionException($e, 'authentication');
            }
        });
    }

    /**
     * Create an authenticated HTTP client.
     */
    private function client(): PendingRequest
    {
        return Http::timeout($this->timeout)
            ->withToken($this->getAccessToken())
            ->acceptJson();
    }

    /**
     * Execute a request with retry logic.
     *
     * @param  callable(): Response  $requestFn
     *
     * @throws PlentySystemException
     */
    private function executeWithRetry(callable $requestFn, string $operation): Response
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                $response = $requestFn();

                if ($response->successful()) {
                    return $response;
                }

                if ($response->status() === 401) {
                    $this->clearTokenCache();
                    throw PlentySystemException::authentication(
                        "Authentication failed during {$operation}"
                    );
                }

                if ($response->serverError()) {
                    Log::warning("PlentySystem server error during {$operation}", [
                        'attempt' => $attempt,
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);

                    if ($attempt < $this->maxRetries) {
                        $this->sleep($attempt);

                        continue;
                    }

                    throw PlentySystemException::serverError(
                        "Server error during {$operation}: ".$response->body()
                    );
                }

                throw new PlentySystemException(
                    "Request failed during {$operation}: ".$response->body(),
                    PlentySystemException::TYPE_UNKNOWN,
                    $response->status()
                );

            } catch (ConnectionException $e) {
                $lastException = $e;

                Log::warning("PlentySystem connection error during {$operation}", [
                    'attempt' => $attempt,
                    'message' => $e->getMessage(),
                ]);

                if ($attempt < $this->maxRetries) {
                    $this->sleep($attempt);

                    continue;
                }

                $this->handleConnectionException($e, $operation);
            }
        }

        throw PlentySystemException::connection(
            "Failed after {$this->maxRetries} attempts during {$operation}",
            $lastException
        );
    }

    /**
     * Sleep with exponential backoff.
     */
    private function sleep(int $attempt): void
    {
        $delayMs = $this->retryDelayMs * (2 ** ($attempt - 1));
        usleep($delayMs * 1000);
    }

    /**
     * Handle connection exceptions and throw the appropriate PlentySystemException.
     *
     * @throws PlentySystemException
     */
    private function handleConnectionException(ConnectionException $e, string $operation): never
    {
        $message = strtolower($e->getMessage());

        if (str_contains($message, 'timed out') || str_contains($message, 'timeout')) {
            throw PlentySystemException::timeout(
                "Request timed out during {$operation}",
                $e
            );
        }

        if (str_contains($message, 'could not resolve') || str_contains($message, 'connection refused')) {
            throw PlentySystemException::connection(
                "Could not connect to PlentySystem during {$operation}",
                $e
            );
        }

        throw PlentySystemException::connection(
            "Connection error during {$operation}: ".$e->getMessage(),
            $e
        );
    }

    /**
     * Get all orders with pagination support.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     *
     * @throws PlentySystemException
     */
    public function getOrders(array $params = []): array
    {
        $defaultParams = [
            'with' => ['addresses', 'addressRelations', 'orderItems'],
        ];

        $mergedParams = array_merge($defaultParams, $params);

        $response = $this->executeWithRetry(
            fn () => $this->client()->get("{$this->baseUrl}/rest/orders", $mergedParams),
            'fetching orders'
        );

        return $response->json();
    }

    /**
     * Get all orders for a date range (handles pagination).
     *
     * @param  array<int>  $orderTypes  Filter by order type IDs (1=Sales, 2=Delivery, 3=Returns, etc.)
     * @return array<int, array<string, mixed>>
     *
     * @throws PlentySystemException
     */
    public function getOrdersForDateRange(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        array $orderTypes = []
    ): array {
        $allOrders = [];
        $page = 1;
        $itemsPerPage = 250;

        do {
            $params = [
                'with' => ['addresses', 'addressRelations', 'orderItems'],
                'createdAtFrom' => $startDate->format('Y-m-d\TH:i:sP'),
                'createdAtTo' => $endDate->format('Y-m-d\TH:i:sP'),
                'page' => $page,
                'itemsPerPage' => $itemsPerPage,
            ];

            if (! empty($orderTypes)) {
                $params['orderTypes'] = implode(',', $orderTypes);
            }

            $response = $this->executeWithRetry(
                fn () => $this->client()->get("{$this->baseUrl}/rest/orders", $params),
                "fetching orders (page {$page})"
            );

            $data = $response->json();
            $entries = $data['entries'] ?? [];
            $allOrders = array_merge($allOrders, $entries);

            $isLastPage = $data['isLastPage'] ?? true;
            $page++;

        } while (! $isLastPage);

        return $allOrders;
    }

    /**
     * Get a single order by ID.
     *
     * @return array<string, mixed>
     *
     * @throws PlentySystemException
     */
    public function getOrder(int $orderId): array
    {
        $response = $this->executeWithRetry(
            fn () => $this->client()->get("{$this->baseUrl}/rest/orders/{$orderId}", [
                'with' => ['addresses', 'addressRelations', 'orderItems'],
            ]),
            "fetching order {$orderId}"
        );

        return $response->json();
    }

    /**
     * Get all order statuses.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getOrderStatuses(): array
    {
        return Cache::remember('plentysystem_order_statuses', 86400, function () {
            $response = $this->executeWithRetry(
                fn () => $this->client()->get("{$this->baseUrl}/rest/orders/statuses"),
                'fetching order statuses'
            );

            return $response->json();
        });
    }

    /**
     * Get all countries.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getCountries(): array
    {
        return Cache::remember('plentysystem_countries', 86400, function () {
            $response = $this->executeWithRetry(
                fn () => $this->client()->get("{$this->baseUrl}/rest/orders/shipping/countries"),
                'fetching countries'
            );

            return $response->json();
        });
    }

    /**
     * Get country name by ID.
     */
    public function getCountryName(int $countryId): ?string
    {
        try {
            $countries = $this->getCountries();

            foreach ($countries as $country) {
                if (($country['id'] ?? null) === $countryId) {
                    return $country['name'] ?? null;
                }
            }
        } catch (PlentySystemException) {
            Log::warning("Failed to get country name for ID {$countryId}");
        }

        return null;
    }

    /**
     * Extract country information from an order.
     *
     * @param  array<string, mixed>  $order
     * @return array{billing_country_id: int|null, billing_country_name: string|null, delivery_country_id: int|null, delivery_country_name: string|null}
     */
    public function extractOrderCountries(array $order): array
    {
        $billingCountryId = null;
        $deliveryCountryId = null;

        $addresses = $order['addresses'] ?? [];
        $addressRelations = $order['addressRelations'] ?? [];

        foreach ($addressRelations as $relation) {
            $typeId = $relation['typeId'] ?? null;
            $addressId = $relation['addressId'] ?? null;

            if ($addressId === null) {
                continue;
            }

            foreach ($addresses as $address) {
                if (($address['id'] ?? null) === $addressId) {
                    $countryId = $address['countryId'] ?? null;

                    if ($typeId === 1) {
                        $billingCountryId = $countryId;
                    } elseif ($typeId === 2) {
                        $deliveryCountryId = $countryId;
                    }

                    break;
                }
            }
        }

        return [
            'billing_country_id' => $billingCountryId,
            'billing_country_name' => $billingCountryId ? $this->getCountryName($billingCountryId) : null,
            'delivery_country_id' => $deliveryCountryId,
            'delivery_country_name' => $deliveryCountryId ? $this->getCountryName($deliveryCountryId) : null,
        ];
    }

    /**
     * Clear the cached access token.
     */
    public function clearTokenCache(): void
    {
        Cache::forget('plentysystem_access_token');
    }

    /**
     * Clear the cached countries.
     */
    public function clearCountriesCache(): void
    {
        Cache::forget('plentysystem_countries');
    }

    /**
     * Get all item variations with pagination.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws PlentySystemException
     */
    public function getVariations(): array
    {
        $allVariations = [];
        $page = 1;
        $itemsPerPage = 250;

        do {
            $response = $this->executeWithRetry(
                fn () => $this->client()->get("{$this->baseUrl}/rest/items/variations", [
                    'page' => $page,
                    'itemsPerPage' => $itemsPerPage,
                ]),
                "fetching variations (page {$page})"
            );

            $data = $response->json();
            $entries = $data['entries'] ?? [];
            $allVariations = array_merge($allVariations, $entries);

            $isLastPage = $data['isLastPage'] ?? true;
            $page++;

        } while (! $isLastPage);

        return $allVariations;
    }
}
