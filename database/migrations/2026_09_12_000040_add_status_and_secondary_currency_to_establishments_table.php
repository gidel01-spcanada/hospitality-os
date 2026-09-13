<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('establishments', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('currency');
            $table->string('secondary_currency', 3)->nullable()->after('is_active');
            $table->decimal('secondary_currency_rate', 14, 6)->nullable()->after('secondary_currency');
        });
    }

    public function down(): void
    {
        Schema::table('establishments', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'secondary_currency', 'secondary_currency_rate']);
        });
    }
};
