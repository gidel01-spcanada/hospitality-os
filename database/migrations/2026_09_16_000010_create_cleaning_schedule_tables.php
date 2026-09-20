<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cleaning_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('assignee_name');
            $table->dateTime('scheduled_at');
            $table->unsignedSmallInteger('duration_minutes')->default(120);
            $table->string('status')->default('scheduled');
            $table->text('instructions')->nullable();
            $table->timestamps();
            $table->index(['property_id', 'scheduled_at']);
        });

        Schema::create('cleaning_schedule_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('token', 64)->unique();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('view_mode', 10);
            $table->json('property_ids');
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cleaning_schedule_shares');
        Schema::dropIfExists('cleaning_visits');
    }
};