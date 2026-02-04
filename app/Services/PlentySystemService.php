<?php

namespace App\Services;

use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class PlentySystemService
{
    private string $baseUrl;

    private string $username;

    private string $password;

    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('plentysystem.base_url'), '/');
        $this->username = (string) config('plentysystem.username');
        $this->password = (string) config('plentysystem.password');
        $this->timeout = (int) config('plentysystem.timeout', 30);
    }

    /**
     * Get the access token, caching it for 23 hours (token valid for 24 hours).
     *
     *
     * @throws Exception
     */
    public function getAccessToken(): string
    {
        return Cache::remember('plentysystem_access_token', 82800, function () {
            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/rest/login", [
                    'username' => $this->username,
                    'password' => $this->password,
                ]);

            if (! $response->successful()) {
                throw new Exception('Failed to authenticate with PlentySystem API: '.$response->body());
            }

            return $response->json('accessToken');
        });
    }

    /**
     * Create an authenticated HTTP client.
     *
     * @throws Exception
     */
    private function client(): PendingRequest
    {
        return Http::timeout($this->timeout)
            ->withToken($this->getAccessToken())
            ->acceptJson();
    }

    /**
     * Get all orders with pagination support.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     *
     * @throws ConnectionException
     * @throws Exception
     */
    public function getOrders(array $params = []): array
    {
        $defaultParams = [
            'with' => ['addresses', 'addressRelations'],
        ];

        $response = $this->client()
            ->get("{$this->baseUrl}/rest/orders", array_merge($defaultParams, $params));

        if (! $response->successful()) {
            throw new Exception('Failed to fetch orders: '.$response->body());
        }

        return $response->json();
    }

    /**
     * Get all orders for a date range (handles pagination).
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws ConnectionException
     * @throws Exception
     */
    public function getOrdersForDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $allOrders = [];
        $page = 1;
        $itemsPerPage = 250;

        do {
            $params = [
                'with' => ['addresses', 'addressRelations'],
                'createdAtFrom' => $startDate->format('Y-m-d\TH:i:sP'),
                'createdAtTo' => $endDate->format('Y-m-d\TH:i:sP'),
                'page' => $page,
                'itemsPerPage' => $itemsPerPage,
            ];

            $response = $this->client()
                ->get("{$this->baseUrl}/rest/orders", $params);

            if (! $response->successful()) {
                throw new Exception('Failed to fetch orders: '.$response->body());
            }

            $data = $response->json();
            $entries = $data['entries'] ?? [];
            dd($entries);
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
     * @throws ConnectionException
     * @throws Exception
     */
    public function getOrder(int $orderId): array
    {
        $response = $this->client()
            ->get("{$this->baseUrl}/rest/orders/{$orderId}", [
                'with' => ['addresses', 'addressRelations'],
            ]);

        if (! $response->successful()) {
            throw new Exception("Failed to fetch order {$orderId}: ".$response->body());
        }

        return $response->json();
    }

    /**
     * Get all countries.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getCountries(): array
    {
        return Cache::remember('plentysystem_countries', 86400, function () {
            $response = $this->client()
                ->get("{$this->baseUrl}/rest/orders/shipping/countries");

            if (! $response->successful()) {
                throw new Exception('Failed to fetch countries: '.$response->body());
            }

            return $response->json();
        });
    }

    /**
     * Get country name by ID.
     */
    public function getCountryName(int $countryId): ?string
    {
        $countries = $this->getCountries();

        foreach ($countries as $country) {
            if (($country['id'] ?? null) === $countryId) {
                return $country['name'] ?? null;
            }
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
        dd($addresses);
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
}
