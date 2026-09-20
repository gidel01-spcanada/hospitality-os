<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('establishments', function (Blueprint $table) {
            $table->string('review_channel', 20)->default('internal')->after('google_maps_url');
            $table->string('google_review_url', 2048)->nullable()->after('review_channel');
        });

        Schema::table('site_reviews', function (Blueprint $table) {
            $table->foreignId('reservation_id')->nullable()->unique()->after('property_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('site_reviews', function (Blueprint $table) {
            $table->dropForeign(['reservation_id']);
            $table->dropUnique(['reservation_id']);
            $table->dropColumn('reservation_id');
        });
        Schema::table('establishments', function (Blueprint $table) {
            $table->dropColumn(['review_channel', 'google_review_url']);
        });
    }
};