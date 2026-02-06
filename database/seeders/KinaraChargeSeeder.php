<?php

namespace Database\Seeders;

use App\Models\KinaraCharge;
use Illuminate\Database\Seeder;

class KinaraChargeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Calculation basis types:
     * - flat: Fixed amount per order
     * - per_item: Amount × total quantity (NOT USED - all charges are flat except second_pick and tablet_config)
     * - per_additional_item: Amount × (quantity - 1)
     * - per_tablet: Amount × tablet count in order
     *
     * Based on reference-sheet-final.xlsx
     */
    public function run(): void
    {
        $charges = [
            // Per-order charges (all flat per order)
            [
                'name' => 'Warehouse Processing Charge',
                'slug' => 'warehouse_processing',
                'amount' => 0.25,
                'charge_type' => 'per_order',
                'calculation_basis' => 'flat',
            ],
            [
                'name' => 'Picking Charge',
                'slug' => 'picking_charge',
                'amount' => 1.62,
                'charge_type' => 'per_order',
                'calculation_basis' => 'flat',
            ],
            [
                'name' => 'Second Pick',
                'slug' => 'second_pick',
                'amount' => 0.30,
                'charge_type' => 'per_order',
                'calculation_basis' => 'per_additional_item',
            ],
            [
                'name' => 'Pack Shipment',
                'slug' => 'pack_shipment',
                'amount' => 0.71,
                'charge_type' => 'per_order',
                'calculation_basis' => 'flat',
            ],
            [
                'name' => 'Packaging Material',
                'slug' => 'packaging_material',
                'amount' => 0.45,
                'charge_type' => 'per_order',
                'calculation_basis' => 'flat',
            ],
            [
                'name' => 'Technology Fee',
                'slug' => 'technology_fee',
                'amount' => 0.50,
                'charge_type' => 'per_order',
                'calculation_basis' => 'flat',
            ],
            [
                'name' => 'Tablet Configuration',
                'slug' => 'tablet_configuration',
                'amount' => 5.55,
                'charge_type' => 'per_order',
                'calculation_basis' => 'per_tablet',
            ],
            // Monthly fixed charges (excluded from Kinara 8%)
            [
                'name' => 'Portal',
                'slug' => 'portal',
                'amount' => 75.00,
                'charge_type' => 'monthly',
                'calculation_basis' => 'flat',
            ],
            [
                'name' => 'Account Management Fee',
                'slug' => 'account_management_fee',
                'amount' => 1200.00,
                'charge_type' => 'monthly',
                'calculation_basis' => 'flat',
            ],
        ];

        // Remove old charges that are no longer used
        KinaraCharge::whereIn('slug', [
            'shipping_charge',
            'kinara_storage',
            'equipment_inbound_fee',
        ])->delete();

        foreach ($charges as $charge) {
            KinaraCharge::updateOrCreate(
                ['slug' => $charge['slug']],
                $charge
            );
        }
    }
}
