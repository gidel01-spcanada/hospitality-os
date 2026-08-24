<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amenities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('category')->default('general');
            $table->timestamps();
        });

        Schema::create('property_amenities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('amenity_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['property_id', 'amenity_id']);
            $table->index('property_id');
            $table->index('amenity_id');
        });

        Schema::create('property_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('mime_type')->nullable();
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_cover')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['property_id', 'sort_order']);
            $table->index(['property_id', 'is_cover']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_images');
        Schema::dropIfExists('property_amenities');
        Schema::dropIfExists('amenities');
    }
};
