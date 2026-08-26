<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('amenities', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('id')->constrained('amenity_categories')->nullOnDelete();
            $table->string('name_en')->nullable()->after('category_id');
            $table->string('name_fr')->nullable()->after('name_en');
            $table->unsignedInteger('sort_order')->default(0)->after('name_fr');
        });

        // Carry the old single-language name into both locales so existing rows stay usable
        // until the reference seeder replaces them with the full, categorized list.
        DB::table('amenities')->get()->each(function ($amenity) {
            DB::table('amenities')->where('id', $amenity->id)->update([
                'name_en' => $amenity->name,
                'name_fr' => $amenity->name,
            ]);
        });

        Schema::table('amenities', function (Blueprint $table) {
            $table->dropColumn(['name', 'category']);
        });
    }

    public function down(): void
    {
        Schema::table('amenities', function (Blueprint $table) {
            $table->string('name')->nullable()->after('id');
            $table->string('category')->default('general')->after('name');
        });

        DB::table('amenities')->get()->each(function ($amenity) {
            DB::table('amenities')->where('id', $amenity->id)->update([
                'name' => $amenity->name_en,
            ]);
        });

        Schema::table('amenities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn(['name_en', 'name_fr', 'sort_order']);
        });
    }
};
