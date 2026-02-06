<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class ShippingRate extends Model
{
    protected $fillable = [
        'country_name',
        'plenty_country_id',
        'amount',
        'carrier',
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
     * Get shipping rate by PlentyMarkets country ID.
     */
    public static function getByCountryId(?int $countryId): ?self
    {
        if ($countryId === null) {
            return null;
        }

        return Cache::remember("shipping_rate_{$countryId}", 3600, function () use ($countryId) {
            return self::query()
                ->where('plenty_country_id', $countryId)
                ->where('is_active', true)
                ->first();
        });
    }

    /**
     * Get shipping amount for a country, returns 0 if not found.
     */
    public static function getAmountByCountryId(?int $countryId): float
    {
        $rate = self::getByCountryId($countryId);

        return $rate ? (float) $rate->amount : 0.0;
    }

    /**
     * Get all active shipping rates.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, ShippingRate>
     */
    public static function getActiveRates(): \Illuminate\Database\Eloquent\Collection
    {
        return self::query()
            ->where('is_active', true)
            ->orderBy('country_name')
            ->get();
    }

    /**
     * Clear the shipping rate cache.
     */
    public static function clearCache(): void
    {
        $rates = self::all();

        foreach ($rates as $rate) {
            Cache::forget("shipping_rate_{$rate->plenty_country_id}");
        }
    }
}
