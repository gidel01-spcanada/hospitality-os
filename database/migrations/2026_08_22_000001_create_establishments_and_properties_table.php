<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('establishments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('country_code', 2)->default('BJ');
            $table->string('city')->nullable();
            $table->string('currency', 3)->default('XOF');
            $table->text('description')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['slug', 'city']);
        });

        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establishment_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status')->default('draft');
            $table->boolean('is_published')->default(false);
            $table->string('currency', 3)->default('XOF');
            $table->decimal('nightly_rate_xof', 12, 2)->default(0);
            $table->decimal('nightly_rate_eur', 12, 2)->default(0);
            $table->integer('max_guests')->default(2);
            $table->integer('bedrooms')->default(1);
            $table->integer('bathrooms')->default(1);
            $table->integer('beds')->default(1);
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('summary')->nullable();
            $table->longText('description')->nullable();
            $table->string('cover_image')->nullable();
            $table->integer('minimum_stay')->default(1);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['establishment_id', 'status']);
            $table->index(['is_published', 'city']);
            $table->index('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('properties');
        Schema::dropIfExists('establishments');
    }
};
