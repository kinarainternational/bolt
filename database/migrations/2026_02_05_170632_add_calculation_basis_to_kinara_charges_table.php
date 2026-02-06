<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('kinara_charges', function (Blueprint $table) {
            // Calculation basis: flat, per_item, per_additional_item, per_tablet
            $table->string('calculation_basis')->default('flat')->after('charge_type');
        });

        // Remove tablet_only column as we now use calculation_basis
        Schema::table('kinara_charges', function (Blueprint $table) {
            $table->dropColumn('tablet_only');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kinara_charges', function (Blueprint $table) {
            $table->dropColumn('calculation_basis');
        });

        Schema::table('kinara_charges', function (Blueprint $table) {
            $table->boolean('tablet_only')->default(false)->after('amount');
        });
    }
};
