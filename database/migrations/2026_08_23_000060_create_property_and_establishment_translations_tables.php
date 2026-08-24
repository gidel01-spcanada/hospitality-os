<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('name')->nullable();
            $table->text('summary')->nullable();
            $table->longText('description')->nullable();
            $table->timestamps();
            $table->unique(['property_id', 'locale']);
        });

        Schema::create('establishment_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establishment_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->json('features')->nullable();
            $table->timestamps();
            $table->unique(['establishment_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('establishment_translations');
        Schema::dropIfExists('property_translations');
    }
};