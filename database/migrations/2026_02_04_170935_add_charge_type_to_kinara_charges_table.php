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
            $table->string('charge_type')->default('per_order')->after('tablet_only');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kinara_charges', function (Blueprint $table) {
            $table->dropColumn('charge_type');
        });
    }
};
