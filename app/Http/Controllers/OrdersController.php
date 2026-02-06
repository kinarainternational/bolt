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
     * Tablet variation ID.
     * Only Bolt Tablet V3 (variation 1139) is considered a tablet.
     */
    private const int TABLET_VARIATION_ID = 1139;

    /**
     * Order types to include in billing calculations.
     * 1 = Sales Order
     *
     * @see https://developers.plentymarkets.com/en-gb/developers/main/rest-api-guides/order-data.html
     *
     * @var array<int>
     */
    private const array BILLABLE_ORDER_TYPES = [
        1, // Sales Order
    ];

    /**
     * Minimum status ID for billable orders (shipped/delivered).
     * Status 7 = "Outgoing items booked" (shipped)
     * Status 7.02 = "Delivered"
     */
    private const float STATUS_MIN = 7.0;

    /**
     * Maximum status ID for billable orders.
     * Excludes 8+ (canceled) and 9+ (returns).
     */
    private const float STATUS_MAX = 8.0;

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

            // Filter to only shipped/delivered orders (status 7.x)
            $billableOrders = $this->filterByStatus($allOrders);

            $groupedByCountry = $this->processOrders($billableOrders);

            return Inertia::render('Orders/Index', [
                'groupedOrders' => array_values($groupedByCountry),
                'totalOrders' => count($billableOrders),
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
            $totalQuantity = $this->countOrderQuantity($order);
            $tabletCount = $this->countTablets($order);
            $deliveryCountryId = $countries['delivery_country_id'];
            $charges = KinaraCharge::calculateOrderTotalWithShipping($totalQuantity, $tabletCount, $deliveryCountryId);
            $chargesItemized = KinaraCharge::getOrderChargesItemized($totalQuantity, $tabletCount, $deliveryCountryId);

            $orderWithExtras = array_merge($order, [
                'countries' => $countries,
                'total_quantity' => $totalQuantity,
                'tablet_count' => $tabletCount,
                'charges' => $charges,
                'charges_itemized' => $chargesItemized,
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
                    'total_quantity' => 0,
                    'total_tablets' => 0,
                    'total_charges' => 0,
                    'currency' => 'EUR',
                ];
            }

            $totalQuantity = $this->countOrderQuantity($order);
            $tabletCount = $this->countTablets($order);
            $charges = KinaraCharge::calculateOrderTotalWithShipping($totalQuantity, $tabletCount, $countryId);

            $orderWithExtras = array_merge($order, [
                'countries' => $countries,
                'total_quantity' => $totalQuantity,
                'tablet_count' => $tabletCount,
                'charges' => $charges,
            ]);

            $groupedByCountry[$countryName]['orders'][] = $orderWithExtras;
            $groupedByCountry[$countryName]['order_count']++;
            $groupedByCountry[$countryName]['total_quantity'] += $totalQuantity;
            $groupedByCountry[$countryName]['total_tablets'] += $tabletCount;
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
     * Filter orders to only include shipped/delivered orders (status 7.x).
     *
     * @param  array<int, array<string, mixed>>  $orders
     * @return array<int, array<string, mixed>>
     */
    private function filterByStatus(array $orders): array
    {
        return array_filter($orders, function (array $order): bool {
            $statusId = (float) ($order['statusId'] ?? 0);

            return $statusId >= self::STATUS_MIN && $statusId < self::STATUS_MAX;
        });
    }

    /**
     * Count the total quantity of items in an order (excluding shipping and other non-product items).
     *
     * @param  array<string, mixed>  $order
     */
    private function countOrderQuantity(array $order): int
    {
        $orderItems = $order['orderItems'] ?? [];
        $totalQuantity = 0;

        foreach ($orderItems as $item) {
            // typeId 1 = product item, skip shipping (6), discounts, etc.
            if (($item['typeId'] ?? 0) === 1 && ($item['itemVariationId'] ?? 0) > 0) {
                $totalQuantity += (int) ($item['quantity'] ?? 1);
            }
        }

        return $totalQuantity;
    }

    /**
     * Count the number of tablets in an order.
     *
     * @param  array<string, mixed>  $order
     */
    private function countTablets(array $order): int
    {
        $orderItems = $order['orderItems'] ?? [];
        $tabletCount = 0;

        foreach ($orderItems as $item) {
            if (($item['itemVariationId'] ?? 0) === self::TABLET_VARIATION_ID) {
                $tabletCount += (int) ($item['quantity'] ?? 1);
            }
        }

        return $tabletCount;
    }
}
