<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_calendar_feeds', function (Blueprint $table) {
            $table->string('last_sync_error')->nullable()->after('last_successful_sync_at');
            $table->string('status')->default('stale')->after('is_enabled');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('external_calendar_feeds', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['last_sync_error', 'status']);
        });
    }
};
