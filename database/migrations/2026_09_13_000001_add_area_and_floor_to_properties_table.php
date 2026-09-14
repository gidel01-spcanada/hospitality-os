<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table): void {
            $table->decimal('area', 10, 2)->nullable()->after('beds');
            $table->string('area_unit', 4)->default('m2')->after('area');
            $table->integer('floor')->nullable()->after('area_unit');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table): void {
            $table->dropColumn(['area', 'area_unit', 'floor']);
        });
    }
};
