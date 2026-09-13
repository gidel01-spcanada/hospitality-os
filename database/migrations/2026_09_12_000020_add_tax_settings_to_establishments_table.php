<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('establishments', function (Blueprint $table) {
            $table->decimal('vat_percent', 5, 2)->default(18.00)->after('cancellation_fee_days');
            $table->boolean('vat_included')->default(true)->after('vat_percent');
            $table->string('city_tax_type', 30)->default('percent')->after('vat_included');
            $table->decimal('city_tax_amount', 10, 2)->default(5.00)->after('city_tax_type');
            $table->decimal('service_fee_percent', 5, 2)->default(10.00)->after('city_tax_amount');
        });
    }

    public function down(): void
    {
        Schema::table('establishments', function (Blueprint $table) {
            $table->dropColumn([
                'vat_percent',
                'vat_included',
                'city_tax_type',
                'city_tax_amount',
                'service_fee_percent',
            ]);
        });
    }
};
