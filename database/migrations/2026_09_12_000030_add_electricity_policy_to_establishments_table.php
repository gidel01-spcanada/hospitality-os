<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('establishments', function (Blueprint $table) {
            $table->boolean('electricity_billed_separately')->default(false)->after('service_fee_percent');
            $table->text('electricity_policy_note')->nullable()->after('electricity_billed_separately');
        });
    }

    public function down(): void
    {
        Schema::table('establishments', function (Blueprint $table) {
            $table->dropColumn(['electricity_billed_separately', 'electricity_policy_note']);
        });
    }
};
