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
        Schema::create('shipping_rates', function (Blueprint $table) {
            $table->id();
            $table->string('country_name');
            $table->unsignedInteger('plenty_country_id')->nullable();
            $table->decimal('amount', 8, 2);
            $table->string('carrier')->default('FedEx Economy');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('plenty_country_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_rates');
    }
};
