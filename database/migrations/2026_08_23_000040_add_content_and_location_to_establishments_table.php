<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('establishments', function (Blueprint $table) {
            $table->string('cover_image')->nullable()->after('description');
            $table->string('address')->nullable()->after('city');
            $table->decimal('latitude', 10, 7)->nullable()->after('address');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->string('google_maps_url', 2048)->nullable()->after('longitude');
            $table->json('features')->nullable()->after('google_maps_url');
        });
    }

    public function down(): void
    {
        Schema::table('establishments', function (Blueprint $table) {
            $table->dropColumn(['cover_image', 'address', 'latitude', 'longitude', 'google_maps_url', 'features']);
        });
    }
};