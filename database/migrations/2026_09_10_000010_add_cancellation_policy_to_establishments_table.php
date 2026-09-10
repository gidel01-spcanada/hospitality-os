<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('establishments', function (Blueprint $table) {
            $table->decimal('cancellation_fee_percent', 5, 2)->default(0)->after('payment_methods');
            $table->unsignedSmallInteger('cancellation_fee_days')->default(0)->after('cancellation_fee_percent');
        });
    }

    public function down(): void
    {
        Schema::table('establishments', function (Blueprint $table) {
            $table->dropColumn(['cancellation_fee_percent', 'cancellation_fee_days']);
        });
    }
};
