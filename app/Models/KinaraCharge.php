<?php

namespace App\Models;

use App\Models\ShippingRate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class KinaraCharge extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'amount',
        'charge_type',
        'calculation_basis',
        'is_active',
    ];

    /**
     * @return array{amount: string, is_active: string}
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Calculate total per-order charges for an order (excluding shipping).
     *
     * @param  int  $totalQuantity  Total quantity of items in the order
     * @param  int  $tabletCount  Number of tablets in the order
     */
    public static function calculateOrderTotal(int $totalQuantity, int $tabletCount = 0): float
    {
        $charges = self::query()
            ->where('is_active', true)
            ->where('charge_type', 'per_order')
            ->get();

        $total = 0.0;

        foreach ($charges as $charge) {
            $total += self::calculateChargeAmount($charge, $totalQuantity, $tabletCount);
        }

        return $total;
    }

    /**
     * Calculate total per-order charges including shipping.
     *
     * @param  int  $totalQuantity  Total quantity of items in the order
     * @param  int  $tabletCount  Number of tablets in the order
     * @param  int|null  $deliveryCountryId  PlentyMarkets country ID for shipping
     */
    public static function calculateOrderTotalWithShipping(int $totalQuantity, int $tabletCount = 0, ?int $deliveryCountryId = null): float
    {
        $total = self::calculateOrderTotal($totalQuantity, $tabletCount);
        $total += ShippingRate::getAmountByCountryId($deliveryCountryId);

        return $total;
    }

    /**
     * Calculate the amount for a single charge based on its calculation basis.
     *
     * @param  KinaraCharge  $charge  The charge to calculate
     * @param  int  $totalQuantity  Total quantity of items
     * @param  int  $tabletCount  Number of tablets
     */
    public static function calculateChargeAmount(KinaraCharge $charge, int $totalQuantity, int $tabletCount): float
    {
        $amount = (float) $charge->amount;

        return match ($charge->calculation_basis) {
            'flat' => $amount,
            'per_item' => $amount * $totalQuantity,
            'per_additional_item' => $amount * max(0, $totalQuantity - 1),
            'per_tablet' => $amount * $tabletCount,
            default => $amount,
        };
    }

    /**
     * Get itemized charges for an order (for display purposes).
     *
     * @param  int  $totalQuantity  Total quantity of items
     * @param  int  $tabletCount  Number of tablets
     * @param  int|null  $deliveryCountryId  PlentyMarkets country ID for shipping
     * @return array<int, array{name: string, slug: string, unit_amount: float, quantity: int, total: float, calculation_basis: string}>
     */
    public static function getOrderChargesItemized(int $totalQuantity, int $tabletCount = 0, ?int $deliveryCountryId = null): array
    {
        $charges = self::query()
            ->where('is_active', true)
            ->where('charge_type', 'per_order')
            ->get();

        $itemized = [];

        foreach ($charges as $charge) {
            $quantity = match ($charge->calculation_basis) {
                'flat' => 1,
                'per_item' => $totalQuantity,
                'per_additional_item' => max(0, $totalQuantity - 1),
                'per_tablet' => $tabletCount,
                default => 1,
            };

            $total = self::calculateChargeAmount($charge, $totalQuantity, $tabletCount);

            // Only include charges that apply (quantity > 0 or flat rate)
            if ($quantity > 0 || $charge->calculation_basis === 'flat') {
                $itemized[] = [
                    'name' => $charge->name,
                    'slug' => $charge->slug,
                    'unit_amount' => (float) $charge->amount,
                    'quantity' => $quantity,
                    'total' => $total,
                    'calculation_basis' => $charge->calculation_basis,
                ];
            }
        }

        // Add shipping if country is provided
        if ($deliveryCountryId !== null) {
            $shippingRate = ShippingRate::getByCountryId($deliveryCountryId);
            if ($shippingRate) {
                $itemized[] = [
                    'name' => 'Shipping ('.$shippingRate->country_name.')',
                    'slug' => 'shipping',
                    'unit_amount' => (float) $shippingRate->amount,
                    'quantity' => 1,
                    'total' => (float) $shippingRate->amount,
                    'calculation_basis' => 'flat',
                ];
            }
        }

        return $itemized;
    }

    /**
     * Calculate total monthly fixed charges.
     */
    public static function calculateMonthlyTotal(): float
    {
        return (float) self::query()
            ->where('is_active', true)
            ->where('charge_type', 'monthly')
            ->sum('amount');
    }

    /**
     * Get all active per-order charges.
     *
     * @return Collection<int, KinaraCharge>
     */
    public static function getPerOrderCharges(): Collection
    {
        return self::query()
            ->where('is_active', true)
            ->where('charge_type', 'per_order')
            ->get();
    }

    /**
     * Get all active monthly charges.
     *
     * @return Collection<int, KinaraCharge>
     */
    public static function getMonthlyCharges(): Collection
    {
        return self::query()
            ->where('is_active', true)
            ->where('charge_type', 'monthly')
            ->get();
    }
}
