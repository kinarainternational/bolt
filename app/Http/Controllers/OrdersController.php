<?php

namespace App\Http\Controllers;

use App\Exceptions\PlentySystemException;
use App\Models\KinaraCharge;
use App\Services\PlentySystemService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class OrdersController extends Controller
{
    /**
     * Variation IDs that contain tablets (trigger tablet configuration charge).
     *
     * @var array<int>
     */
    private const array TABLET_VARIATION_IDS = [
        1125, // KIBOTBLT - Bolt Tablet
        1138, // BOLTABV2s - Tablet V2s
        1139, // BOLTABV3 - Tablet V3
        1130, // BOLTABV2+STI - Tablet V2 bundle
        1131, // BOLTABV3+STI - Tablet V3 bundle
        1132, // BOLTABV2+STI+TABTO
        1133, // BOLTABV3+STI+TABTO
        1134, // BOLTABV2+SIM
        1135, // BOLTABV3+STI+SIM
        1136, // BOLTABV2+STI+SIM+TABTO
        1137, // BOLTABV3+STI+SIM+TABTO
    ];

    /**
     * Order types to include in billing calculations.
     * 1 = Sales Order, 2 = Delivery, 3 = Returns, etc.
     *
     * @see https://developers.plentymarkets.com/en-gb/developers/main/rest-api-guides/order-data.html
     *
     * @var array<int>
     */
    private const array BILLABLE_ORDER_TYPES = [
        1, // Sales Order
    ];

    public function __construct(private readonly PlentySystemService $plentySystem) {}

    public function index(Request $request): Response
    {
        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);

        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $availableMonths = $this->getAvailableMonths();

        try {
            $allOrders = $this->plentySystem->getOrdersForDateRange(
                $startDate,
                $endDate,
                self::BILLABLE_ORDER_TYPES
            );

            $groupedByCountry = $this->processOrders($allOrders);

            return Inertia::render('Orders/Index', [
                'groupedOrders' => array_values($groupedByCountry),
                'totalOrders' => count($allOrders),
                'filters' => [
                    'year' => $year,
                    'month' => $month,
                ],
                'availableMonths' => $availableMonths,
                'perOrderCharges' => KinaraCharge::getPerOrderCharges(),
                'monthlyCharges' => KinaraCharge::getMonthlyCharges(),
                'monthlyTotal' => KinaraCharge::calculateMonthlyTotal(),
                'error' => null,
            ]);
        } catch (PlentySystemException $e) {
            Log::error('Failed to fetch orders', [
                'error_type' => $e->errorType,
                'message' => $e->getMessage(),
                'year' => $year,
                'month' => $month,
            ]);

            return Inertia::render('Orders/Index', [
                'groupedOrders' => [],
                'totalOrders' => 0,
                'filters' => [
                    'year' => $year,
                    'month' => $month,
                ],
                'availableMonths' => $availableMonths,
                'perOrderCharges' => KinaraCharge::getPerOrderCharges(),
                'monthlyCharges' => KinaraCharge::getMonthlyCharges(),
                'monthlyTotal' => KinaraCharge::calculateMonthlyTotal(),
                'error' => [
                    'message' => $e->getUserMessage(),
                    'type' => $e->errorType,
                    'retryable' => $e->isRetryable(),
                ],
            ]);
        }
    }

    public function show(int $orderId): Response
    {
        try {
            $order = $this->plentySystem->getOrder($orderId);

            $countries = $this->plentySystem->extractOrderCountries($order);
            $skuCount = $this->countOrderSkus($order);
            $hasTablet = $this->orderHasTablet($order);
            $charges = KinaraCharge::calculateOrderTotal($hasTablet);

            $orderWithExtras = array_merge($order, [
                'countries' => $countries,
                'sku_count' => $skuCount,
                'has_tablet' => $hasTablet,
                'charges' => $charges,
            ]);

            return Inertia::render('Orders/Show', [
                'order' => $orderWithExtras,
                'perOrderCharges' => KinaraCharge::getPerOrderCharges(),
                'error' => null,
            ]);
        } catch (PlentySystemException $e) {
            Log::error('Failed to fetch order', [
                'error_type' => $e->errorType,
                'message' => $e->getMessage(),
                'order_id' => $orderId,
            ]);

            return Inertia::render('Orders/Show', [
                'order' => null,
                'perOrderCharges' => KinaraCharge::getPerOrderCharges(),
                'error' => [
                    'message' => $e->getUserMessage(),
                    'type' => $e->errorType,
                    'retryable' => $e->isRetryable(),
                ],
            ]);
        }
    }

    /**
     * Process orders and group them by country.
     *
     * @param  array<int, array<string, mixed>>  $allOrders
     * @return array<string, array<string, mixed>>
     */
    private function processOrders(array $allOrders): array
    {
        $groupedByCountry = [];

        foreach ($allOrders as $order) {
            $countries = $this->plentySystem->extractOrderCountries($order);
            $countryName = $countries['delivery_country_name'] ?? 'Unknown';
            $countryId = $countries['delivery_country_id'];

            if (! isset($groupedByCountry[$countryName])) {
                $groupedByCountry[$countryName] = [
                    'country_name' => $countryName,
                    'country_id' => $countryId,
                    'orders' => [],
                    'order_count' => 0,
                    'total_gross' => 0,
                    'total_skus' => 0,
                    'total_charges' => 0,
                    'currency' => 'EUR',
                ];
            }

            $skuCount = $this->countOrderSkus($order);
            $hasTablet = $this->orderHasTablet($order);
            $charges = KinaraCharge::calculateOrderTotal($hasTablet);

            $orderWithExtras = array_merge($order, [
                'countries' => $countries,
                'sku_count' => $skuCount,
                'has_tablet' => $hasTablet,
                'charges' => $charges,
            ]);

            $groupedByCountry[$countryName]['orders'][] = $orderWithExtras;
            $groupedByCountry[$countryName]['order_count']++;
            $groupedByCountry[$countryName]['total_skus'] += $skuCount;
            $groupedByCountry[$countryName]['total_charges'] += $charges;

            $grossTotal = $order['amounts'][0]['grossTotal'] ?? 0;
            $groupedByCountry[$countryName]['total_gross'] += $grossTotal;

            if (isset($order['amounts'][0]['currency'])) {
                $groupedByCountry[$countryName]['currency'] = $order['amounts'][0]['currency'];
            }
        }

        uasort($groupedByCountry, fn ($a, $b) => $b['order_count'] <=> $a['order_count']);

        return $groupedByCountry;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function getAvailableMonths(): array
    {
        $months = [];
        $current = now()->startOfMonth();

        for ($i = 0; $i < 12; $i++) {
            $date = $current->copy()->subMonths($i);
            $months[] = [
                'value' => $date->format('Y-m'),
                'label' => $date->format('F Y'),
            ];
        }

        return $months;
    }

    /**
     * Count unique SKUs in an order (excluding shipping and other non-product items).
     *
     * @param  array<string, mixed>  $order
     */
    private function countOrderSkus(array $order): int
    {
        $orderItems = $order['orderItems'] ?? [];
        $skuCount = 0;

        foreach ($orderItems as $item) {
            // typeId 1 = product item, skip shipping (6), discounts, etc.
            if (($item['typeId'] ?? 0) === 1 && ($item['itemVariationId'] ?? 0) > 0) {
                $skuCount++;
            }
        }

        return $skuCount;
    }

    /**
     * Check if an order contains a tablet (by variation ID).
     *
     * @param  array<string, mixed>  $order
     */
    private function orderHasTablet(array $order): bool
    {
        $orderItems = $order['orderItems'] ?? [];

        foreach ($orderItems as $item) {
            if (in_array($item['itemVariationId'] ?? 0, self::TABLET_VARIATION_IDS, true)) {
                return true;
            }
        }

        return false;
    }
}
