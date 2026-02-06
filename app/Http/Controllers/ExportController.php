<?php

namespace App\Http\Controllers;

use App\Exceptions\PlentySystemException;
use App\Models\KinaraCharge;
use App\Services\PlentySystemService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    /**
     * Tablet variation ID (Bolt Tablet V3).
     */
    private const int TABLET_VARIATION_ID = 1139;

    private const array BILLABLE_ORDER_TYPES = [1];

    /**
     * Status range for billable orders (shipped/delivered = 7.x).
     */
    private const float STATUS_MIN = 7.0;

    private const float STATUS_MAX = 8.0;

    public function __construct(private readonly PlentySystemService $plentySystem) {}

    public function export(Request $request): StreamedResponse|RedirectResponse
    {
        $validated = $request->validate([
            'year' => 'required|integer',
            'month' => 'required|integer|min:1|max:12',
            'warehouse_workers_hours' => 'required|numeric|min:0',
            'warehouse_workers_rate' => 'required|numeric|min:0',
            'inbound_pallets' => 'required|numeric|min:0',
            'pallet_storage_count' => 'required|numeric|min:0',
            'returns_count' => 'required|numeric|min:0',
            'reset_tablet_count' => 'required|numeric|min:0',
        ]);

        $year = (int) $validated['year'];
        $month = (int) $validated['month'];

        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        try {
            $allOrders = $this->plentySystem->getOrdersForDateRange(
                $startDate,
                $endDate,
                self::BILLABLE_ORDER_TYPES
            );
        } catch (PlentySystemException $e) {
            Log::error('Failed to fetch orders for export', [
                'error_type' => $e->errorType,
                'message' => $e->getMessage(),
                'year' => $year,
                'month' => $month,
            ]);

            return redirect()
                ->back()
                ->with('error', $e->getUserMessage());
        }

        // Filter to only shipped/delivered orders (status 7.x)
        $billableOrders = $this->filterByStatus($allOrders);

        $groupedByCountry = $this->groupOrdersByCountry($billableOrders);

        $spreadsheet = $this->createSpreadsheet(
            $groupedByCountry,
            $startDate,
            $validated
        );

        $filename = sprintf('reference-sheet-%s-%02d.xlsx', $year, $month);

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $orders
     * @return array<string, array<string, mixed>>
     */
    private function groupOrdersByCountry(array $orders): array
    {
        $grouped = [];

        foreach ($orders as $order) {
            $countries = $this->plentySystem->extractOrderCountries($order);
            $countryName = $countries['delivery_country_name'] ?? 'Unknown';
            $countryId = $countries['delivery_country_id'];

            if (! isset($grouped[$countryName])) {
                $grouped[$countryName] = [
                    'country_name' => $countryName,
                    'country_id' => $countryId,
                    'orders' => [],
                    'total' => 0,
                ];
            }

            $totalQuantity = $this->countOrderQuantity($order);
            $tabletCount = $this->countTablets($order);
            $chargesItemized = KinaraCharge::getOrderChargesItemized($totalQuantity, $tabletCount, $countryId);
            $orderTotal = KinaraCharge::calculateOrderTotalWithShipping($totalQuantity, $tabletCount, $countryId);

            $grouped[$countryName]['orders'][] = [
                'id' => $order['id'],
                'total_quantity' => $totalQuantity,
                'tablet_count' => $tabletCount,
                'charges_itemized' => $chargesItemized,
                'total' => $orderTotal,
            ];

            $grouped[$countryName]['total'] += $orderTotal;
        }

        ksort($grouped);

        return $grouped;
    }

    /**
     * @param  array<string, array<string, mixed>>  $groupedOrders
     * @param  array<string, mixed>  $variableCharges
     */
    private function createSpreadsheet(array $groupedOrders, Carbon $date, array $variableCharges): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Reference Sheet');

        $row = 1;

        // Title
        $sheet->setCellValue('A'.$row, 'Reference Sheet');
        $sheet->getStyle('A'.$row)->getFont()->setBold(true)->setSize(16);
        $row += 2;

        // Month/Year
        $sheet->setCellValue('A'.$row, $date->format('F Y'));
        $sheet->getStyle('A'.$row)->getFont()->setBold(true)->setSize(14);
        $row += 2;

        // Get charge names for headers
        $perOrderCharges = KinaraCharge::getPerOrderCharges();
        $chargeNames = $perOrderCharges->pluck('name')->toArray();

        $countryTotals = [];

        // Orders by country
        foreach ($groupedOrders as $countryName => $countryData) {
            // Country header
            $sheet->setCellValue('A'.$row, $countryName);
            $sheet->getStyle('A'.$row)->getFont()->setBold(true)->setSize(12);
            $sheet->getStyle('A'.$row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('E0E0E0');
            $row++;

            // Table headers
            $headers = ['Order No', 'Qty', 'Tablets', ...$chargeNames, 'Shipping', 'Total'];
            $col = 'A';
            foreach ($headers as $header) {
                $sheet->setCellValue($col.$row, $header);
                $sheet->getStyle($col.$row)->getFont()->setBold(true);
                $sheet->getStyle($col.$row)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('F0F0F0');
                $col++;
            }
            $row++;

            $orderStartRow = $row;

            // Order rows
            foreach ($countryData['orders'] as $order) {
                $col = 'A';
                $sheet->setCellValue($col++.$row, $order['id']);
                $sheet->setCellValue($col++.$row, $order['total_quantity']);
                $sheet->setCellValue($col++.$row, $order['tablet_count']);

                foreach ($perOrderCharges as $charge) {
                    $chargeItem = collect($order['charges_itemized'])
                        ->firstWhere('slug', $charge->slug);
                    $value = $chargeItem ? $chargeItem['total'] : 0;
                    $sheet->setCellValue($col++.$row, $value > 0 ? $value : '-');
                }

                // Shipping column
                $shippingItem = collect($order['charges_itemized'])
                    ->firstWhere('slug', 'shipping');
                $shippingValue = $shippingItem ? $shippingItem['total'] : 0;
                $sheet->setCellValue($col++.$row, $shippingValue > 0 ? $shippingValue : '-');

                $sheet->setCellValue($col.$row, $order['total']);
                $row++;
            }

            $orderEndRow = $row - 1;
            $totalCol = chr(ord('A') + count($headers) - 1);

            // Country subtotal
            $sheet->setCellValue('A'.$row, 'Subtotal');
            $sheet->setCellValue($totalCol.$row, "=SUM({$totalCol}{$orderStartRow}:{$totalCol}{$orderEndRow})");
            $sheet->getStyle('A'.$row.':'.$totalCol.$row)->getFont()->setBold(true);

            $countryTotals[$countryName] = $totalCol.$row;
            $row += 2;
        }

        // Totals section
        $sheet->setCellValue('A'.$row, 'Country Totals');
        $sheet->getStyle('A'.$row)->getFont()->setBold(true)->setSize(12);
        $row++;

        foreach ($countryTotals as $country => $cellRef) {
            $sheet->setCellValue('A'.$row, $country);
            $sheet->setCellValue('B'.$row, "={$cellRef}");
            $row++;
        }

        $countryTotalsEndRow = $row - 1;
        $countryTotalsStartRow = $countryTotalsEndRow - count($countryTotals) + 1;
        $row += 2;

        // Variable charges section
        $sheet->setCellValue('A'.$row, 'Variable Charges');
        $sheet->setCellValue('B'.$row, 'Input');
        $sheet->setCellValue('C'.$row, 'Total');
        $sheet->getStyle('A'.$row.':C'.$row)->getFont()->setBold(true);
        $sheet->getStyle('A'.$row.':C'.$row)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('E0E0E0');
        $row++;

        $variableChargeRows = [];

        // Warehouse workers
        $variableChargeRows['warehouse'] = $row;
        $sheet->setCellValue('A'.$row, 'Warehouse workers');
        $sheet->setCellValue('B'.$row, $variableCharges['warehouse_workers_hours'].' hrs @ €'.$variableCharges['warehouse_workers_rate']);
        $sheet->setCellValue('C'.$row, $variableCharges['warehouse_workers_hours'] * $variableCharges['warehouse_workers_rate']);
        $row++;

        // Inbound Pallet (€6/pallet)
        $inboundPallets = (float) $variableCharges['inbound_pallets'];
        $variableChargeRows['inbound'] = $row;
        $sheet->setCellValue('A'.$row, 'Inbound Pallet');
        $sheet->setCellValue('B'.$row, $inboundPallets.' pallets @ €6');
        $sheet->setCellValue('C'.$row, $inboundPallets * 6);
        $row++;

        // Pallet storage (€12/pallet/month)
        $palletStorageCount = (float) $variableCharges['pallet_storage_count'];
        $variableChargeRows['pallet'] = $row;
        $sheet->setCellValue('A'.$row, 'Pallet storage / month');
        $sheet->setCellValue('B'.$row, $palletStorageCount.' pallets @ €12');
        $sheet->setCellValue('C'.$row, $palletStorageCount * 12);
        $row++;

        // Returns (€3/return)
        $returnsCount = (float) $variableCharges['returns_count'];
        $variableChargeRows['returns'] = $row;
        $sheet->setCellValue('A'.$row, 'Returns');
        $sheet->setCellValue('B'.$row, $returnsCount.' returns @ €3');
        $sheet->setCellValue('C'.$row, $returnsCount * 3);
        $row++;

        // Reset tablet (€5.55/reset)
        $resetTabletCount = (float) $variableCharges['reset_tablet_count'];
        if ($resetTabletCount > 0) {
            $variableChargeRows['reset'] = $row;
            $sheet->setCellValue('A'.$row, 'Reset tablet');
            $sheet->setCellValue('B'.$row, $resetTabletCount.' resets @ €5.55');
            $sheet->setCellValue('C'.$row, $resetTabletCount * 5.55);
            $row++;
        }

        $row++;

        // Subtotal (before Kinara fee - excludes fixed charges)
        $subtotalRow = $row;
        $variableChargesList = 'C'.implode('+C', $variableChargeRows);
        $sheet->setCellValue('A'.$row, 'Subtotal (before Kinara fee)');
        $sheet->setCellValue('B'.$row, "=SUM(B{$countryTotalsStartRow}:B{$countryTotalsEndRow})+{$variableChargesList}");
        $sheet->getStyle('A'.$row.':B'.$row)->getFont()->setBold(true);
        $row += 2;

        // Kinara percentage (8% of subtotal, excluding fixed charges)
        $sheet->setCellValue('A'.$row, 'Kinara fee (8%)');
        $sheet->setCellValue('B'.$row, "=B{$subtotalRow}*0.08");
        $kinaraRow = $row;
        $row += 2;

        // Subtotal after Kinara
        $sheet->setCellValue('A'.$row, 'Subtotal after Kinara fee');
        $sheet->setCellValue('B'.$row, "=B{$subtotalRow}+B{$kinaraRow}");
        $sheet->getStyle('A'.$row.':B'.$row)->getFont()->setBold(true);
        $subtotalAfterKinaraRow = $row;
        $row += 2;

        // Fixed charges section (excluded from Kinara %)
        $sheet->setCellValue('A'.$row, 'Fixed Charges (excluded from Kinara %)');
        $sheet->getStyle('A'.$row)->getFont()->setBold(true);
        $sheet->getStyle('A'.$row.':B'.$row)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('E0E0E0');
        $row++;

        $fixedChargesStart = $row;
        $monthlyCharges = KinaraCharge::getMonthlyCharges();
        foreach ($monthlyCharges as $charge) {
            $sheet->setCellValue('A'.$row, $charge->name);
            $sheet->setCellValue('B'.$row, $charge->amount);
            $row++;
        }
        $fixedChargesEnd = $row - 1;
        $row++;

        // Grand Total
        $sheet->setCellValue('A'.$row, 'GRAND TOTAL');
        $sheet->setCellValue('B'.$row, "=B{$subtotalAfterKinaraRow}+SUM(B{$fixedChargesStart}:B{$fixedChargesEnd})");
        $sheet->getStyle('A'.$row.':B'.$row)->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A'.$row.':B'.$row)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('90EE90');

        // Auto-size columns
        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Format currency columns
        $sheet->getStyle('B:C')->getNumberFormat()->setFormatCode('#,##0.00');

        return $spreadsheet;
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
     * Count total quantity of items in an order.
     *
     * @param  array<string, mixed>  $order
     */
    private function countOrderQuantity(array $order): int
    {
        $orderItems = $order['orderItems'] ?? [];
        $totalQuantity = 0;

        foreach ($orderItems as $item) {
            if (($item['typeId'] ?? 0) === 1 && ($item['itemVariationId'] ?? 0) > 0) {
                $totalQuantity += (int) ($item['quantity'] ?? 1);
            }
        }

        return $totalQuantity;
    }

    /**
     * Count tablets in an order.
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
