<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('establishments', function (Blueprint $table) {
            $table->string('google_reviews_import_url', 2048)->nullable()->after('google_review_url');
            $table->dateTime('google_reviews_last_sync_at')->nullable()->after('google_reviews_import_url');
            $table->text('google_reviews_last_sync_error')->nullable()->after('google_reviews_last_sync_at');
        });
    }

    public function down(): void
    {
        Schema::table('establishments', function (Blueprint $table) {
            $table->dropColumn(['google_reviews_import_url', 'google_reviews_last_sync_at', 'google_reviews_last_sync_error']);
        });
    }
};