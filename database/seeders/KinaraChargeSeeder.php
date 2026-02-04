<?php

namespace Database\Seeders;

use App\Models\KinaraCharge;
use Illuminate\Database\Seeder;

class KinaraChargeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $charges = [
            // Per-order charges
            [
                'name' => 'Picking Charge',
                'slug' => 'picking_charge',
                'amount' => 1.53,
                'tablet_only' => false,
                'charge_type' => 'per_order',
            ],
            [
                'name' => 'Shipping Charge',
                'slug' => 'shipping_charge',
                'amount' => 1.62,
                'tablet_only' => false,
                'charge_type' => 'per_order',
            ],
            [
                'name' => 'Tablet Configuration',
                'slug' => 'tablet_configuration',
                'amount' => 3.50,
                'tablet_only' => true,
                'charge_type' => 'per_order',
            ],
            [
                'name' => 'Packaging Material',
                'slug' => 'packaging_material',
                'amount' => 0.45,
                'tablet_only' => false,
                'charge_type' => 'per_order',
            ],
            // Monthly fixed charges
            [
                'name' => 'Portal',
                'slug' => 'portal',
                'amount' => 500.00,
                'tablet_only' => false,
                'charge_type' => 'monthly',
            ],
            [
                'name' => 'Account Management Fee',
                'slug' => 'account_management_fee',
                'amount' => 1500.00,
                'tablet_only' => false,
                'charge_type' => 'monthly',
            ],
        ];

        foreach ($charges as $charge) {
            KinaraCharge::updateOrCreate(
                ['slug' => $charge['slug']],
                $charge
            );
        }
    }
}
