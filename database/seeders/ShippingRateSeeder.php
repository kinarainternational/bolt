<?php

namespace Database\Seeders;

use App\Models\ShippingRate;
use Illuminate\Database\Seeder;

class ShippingRateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Shipping rates from contract: FedEx Economy + Surcharge (Delivery 1-2 days)
     * Contract: Services Agreement_Bolt Operations OÜ_Kinara International GmbH_28.10.2025.pdf
     */
    public function run(): void
    {
        $rates = [
            [
                'country_name' => 'Poland',
                'plenty_country_id' => 23,
                'amount' => 7.09,
                'carrier' => 'FedEx Economy',
            ],
            [
                'country_name' => 'Romania',
                'plenty_country_id' => 41,
                'amount' => 11.71,
                'carrier' => 'FedEx Economy',
            ],
            [
                'country_name' => 'Latvia',
                'plenty_country_id' => 18,
                'amount' => 12.66,
                'carrier' => 'FedEx Economy',
            ],
            [
                'country_name' => 'Bulgaria',
                'plenty_country_id' => 44,
                'amount' => 12.19,
                'carrier' => 'FedEx Economy',
            ],
            [
                'country_name' => 'Czech Republic',
                'plenty_country_id' => 6,
                'amount' => 8.00,
                'carrier' => 'FedEx Economy',
            ],
        ];

        foreach ($rates as $rate) {
            ShippingRate::updateOrCreate(
                ['plenty_country_id' => $rate['plenty_country_id']],
                $rate
            );
        }
    }
}
