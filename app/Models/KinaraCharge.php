<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KinaraCharge extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'amount',
        'tablet_only',
        'charge_type',
        'is_active',
    ];

    /**
     * @return array{name: string, slug: string, amount: float, tablet_only: bool, charge_type: string}
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'tablet_only' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Calculate total per-order charges.
     *
     * @param  bool  $hasTablet  Whether the order contains a tablet
     */
    public static function calculateOrderTotal(bool $hasTablet = false): float
    {
        $query = self::query()
            ->where('is_active', true)
            ->where('charge_type', 'per_order');

        if (! $hasTablet) {
            $query->where('tablet_only', false);
        }

        return (float) $query->sum('amount');
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
     * @return \Illuminate\Database\Eloquent\Collection<int, KinaraCharge>
     */
    public static function getPerOrderCharges(): \Illuminate\Database\Eloquent\Collection
    {
        return self::query()
            ->where('is_active', true)
            ->where('charge_type', 'per_order')
            ->get();
    }

    /**
     * Get all active monthly charges.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, KinaraCharge>
     */
    public static function getMonthlyCharges(): \Illuminate\Database\Eloquent\Collection
    {
        return self::query()
            ->where('is_active', true)
            ->where('charge_type', 'monthly')
            ->get();
    }
}
