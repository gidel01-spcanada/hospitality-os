<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('source')->default('google');
            $table->string('reviewer_name')->nullable();
            $table->unsignedTinyInteger('rating')->default(5);
            $table->text('review_text')->nullable();
            $table->string('source_url')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['source', 'is_active']);
            $table->index('reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_reviews');
    }
};
