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
     * Variation IDs that contain tablets.
     *
     * @var array<int>
     */
    private const array TABLET_VARIATION_IDS = [
        1125, // KIBOTBLT - Bolt Tablet
        1138, // BOLTABV2s - Tablet V2s
        1139, // BOLTABV3 - Tablet V3
        1130, // BOLTABV2+STI
        1131, // BOLTABV3+STI
        1132, // BOLTABV2+STI+TABTO
        1133, // BOLTABV3+STI+TABTO
        1134, // BOLTABV2+SIM
        1135, // BOLTABV3+STI+SIM
        1136, // BOLTABV2+STI+SIM+TABTO
        1137, // BOLTABV3+STI+SIM+TABTO
    ];

    private const array BILLABLE_ORDER_TYPES = [1];

    public function __construct(private readonly PlentySystemService $plentySystem) {}

    public function export(Request $request): StreamedResponse|RedirectResponse
    {
        $validated = $request->validate([
            'year' => 'required|integer',
            'month' => 'required|integer|min:1|max:12',
            'warehouse_workers_hours' => 'required|numeric|min:0',
            'warehouse_workers_rate' => 'required|numeric|min:0',
            'inbound' => 'required|numeric|min:0',
            'pallet_storage' => 'required|numeric|min:0',
            'returns' => 'required|numeric|min:0',
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

        $groupedByCountry = $this->groupOrdersByCountry($allOrders);

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

            if (! isset($grouped[$countryName])) {
                $grouped[$countryName] = [
                    'country_name' => $countryName,
                    'orders' => [],
                    'total' => 0,
                ];
            }

            $skuCount = $this->countOrderSkus($order);
            $hasTablet = $this->orderHasTablet($order);

            $pickingCharge = KinaraCharge::where('slug', 'picking_charge')->first()?->amount ?? 1.53;
            $shippingCharge = KinaraCharge::where('slug', 'shipping_charge')->first()?->amount ?? 1.62;
            $tabletConfig = $hasTablet ? (KinaraCharge::where('slug', 'tablet_configuration')->first()?->amount ?? 3.50) : 0;
            $packaging = KinaraCharge::where('slug', 'packaging_material')->first()?->amount ?? 0.45;

            $orderTotal = $pickingCharge + $shippingCharge + $tabletConfig + $packaging;

            $grouped[$countryName]['orders'][] = [
                'id' => $order['id'],
                'sku_count' => $skuCount,
                'has_tablet' => $hasTablet,
                'picking_charge' => $pickingCharge,
                'shipping_charge' => $shippingCharge,
                'tablet_config' => $tabletConfig,
                'packaging' => $packaging,
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
            $headers = ['Order No', '# of SKUs', 'Picking charge', 'Shipping charge', 'Tablet configuration', 'Packaging material', 'Total'];
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
                $sheet->setCellValue('A'.$row, $order['id']);
                $sheet->setCellValue('B'.$row, $order['sku_count']);
                $sheet->setCellValue('C'.$row, $order['picking_charge']);
                $sheet->setCellValue('D'.$row, $order['shipping_charge']);
                $sheet->setCellValue('E'.$row, $order['tablet_config'] > 0 ? $order['tablet_config'] : '-');
                $sheet->setCellValue('F'.$row, $order['packaging']);
                $sheet->setCellValue('G'.$row, "=SUM(C{$row}:F{$row})");
                $row++;
            }

            $orderEndRow = $row - 1;

            // Country subtotal
            $sheet->setCellValue('A'.$row, 'Subtotal');
            $sheet->setCellValue('G'.$row, "=SUM(G{$orderStartRow}:G{$orderEndRow})");
            $sheet->getStyle('A'.$row.':G'.$row)->getFont()->setBold(true);

            $countryTotals[$countryName] = 'G'.$row;
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
        $sheet->setCellValue('A'.$row, 'Variable charges');
        $sheet->setCellValue('B'.$row, '# of hours');
        $sheet->setCellValue('C'.$row, 'Total');
        $sheet->getStyle('A'.$row.':C'.$row)->getFont()->setBold(true);
        $sheet->getStyle('A'.$row.':C'.$row)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('E0E0E0');
        $row++;

        $warehouseRow = $row;
        $sheet->setCellValue('A'.$row, 'Warehouse workers');
        $sheet->setCellValue('B'.$row, $variableCharges['warehouse_workers_hours']);
        $sheet->setCellValue('C'.$row, "=B{$row}*{$variableCharges['warehouse_workers_rate']}");
        $row++;

        $inboundRow = $row;
        $sheet->setCellValue('A'.$row, 'Inbound');
        $sheet->setCellValue('C'.$row, $variableCharges['inbound']);
        $row++;

        $palletRow = $row;
        $sheet->setCellValue('A'.$row, 'Pallet storage');
        $sheet->setCellValue('C'.$row, $variableCharges['pallet_storage']);
        $row++;

        $returnsRow = $row;
        $sheet->setCellValue('A'.$row, 'Returns');
        $sheet->setCellValue('C'.$row, $variableCharges['returns']);
        $row += 2;

        // Fixed charges section
        $sheet->setCellValue('A'.$row, 'Fixed charges');
        $sheet->getStyle('A'.$row)->getFont()->setBold(true);
        $sheet->getStyle('A'.$row.':B'.$row)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('E0E0E0');
        $row++;

        $portalRow = $row;
        $sheet->setCellValue('A'.$row, 'Portal');
        $sheet->setCellValue('B'.$row, KinaraCharge::where('slug', 'portal')->first()?->amount ?? 500);
        $row++;

        $accountFeeRow = $row;
        $sheet->setCellValue('A'.$row, 'Account management fee');
        $sheet->setCellValue('B'.$row, KinaraCharge::where('slug', 'account_management_fee')->first()?->amount ?? 1500);
        $row += 2;

        // Subtotal
        $subtotalRow = $row;
        $sheet->setCellValue('A'.$row, 'Subtotal');
        $sheet->setCellValue('B'.$row, "=SUM(B{$countryTotalsStartRow}:B{$countryTotalsEndRow})+C{$warehouseRow}+C{$inboundRow}+C{$palletRow}+C{$returnsRow}+B{$portalRow}+B{$accountFeeRow}");
        $sheet->getStyle('A'.$row.':B'.$row)->getFont()->setBold(true);
        $row += 2;

        // Kinara percentage
        $sheet->setCellValue('A'.$row, 'Kinara percentage (8%)');
        $sheet->setCellValue('B'.$row, "=B{$subtotalRow}*0.08");
        $percentageRow = $row;
        $row += 2;

        // Grand Total
        $sheet->setCellValue('A'.$row, 'TOTAL');
        $sheet->setCellValue('B'.$row, "=B{$subtotalRow}+B{$percentageRow}");
        $sheet->getStyle('A'.$row.':B'.$row)->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A'.$row.':B'.$row)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('90EE90');

        // Auto-size columns
        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Format currency columns
        $sheet->getStyle('C:G')->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('B'.$subtotalRow)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('B'.$percentageRow)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('B'.$row)->getNumberFormat()->setFormatCode('#,##0.00');

        return $spreadsheet;
    }

    /**
     * @param  array<string, mixed>  $order
     */
    private function countOrderSkus(array $order): int
    {
        $orderItems = $order['orderItems'] ?? [];
        $skuCount = 0;

        foreach ($orderItems as $item) {
            if (($item['typeId'] ?? 0) === 1 && ($item['itemVariationId'] ?? 0) > 0) {
                $skuCount++;
            }
        }

        return $skuCount;
    }

    /**
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
