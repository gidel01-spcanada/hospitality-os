<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_calendar_feeds', function (Blueprint $table) {
            $table->string('provider', 30)->default('other')->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('external_calendar_feeds', function (Blueprint $table) {
            $table->dropColumn('provider');
        });
    }
};