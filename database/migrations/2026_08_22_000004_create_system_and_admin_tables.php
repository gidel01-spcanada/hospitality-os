<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currency_configs', function (Blueprint $table) {
            $table->id();
            $table->string('currency_code', 3)->unique();
            $table->string('name');
            $table->decimal('xof_per_eur', 12, 6)->default(655.957000);
            $table->boolean('is_enabled')->default(true);
            $table->timestamp('effective_at')->nullable();
            $table->timestamps();
        });

        Schema::create('admin_availability_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('reason')->nullable();
            $table->timestamps();
            $table->index(['property_id', 'start_date', 'end_date']);
        });

        Schema::create('external_calendar_feeds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('url');
            $table->boolean('is_enabled')->default(true);
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamp('last_successful_sync_at')->nullable();
            $table->integer('sync_interval_minutes')->default(15);
            $table->timestamps();
            $table->index(['property_id', 'is_enabled']);
        });

        Schema::create('external_calendar_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feed_id')->constrained('external_calendar_feeds')->cascadeOnDelete();
            $table->string('uid')->unique();
            $table->string('summary')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamps();
            $table->index(['feed_id', 'start_date']);
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string');
            $table->timestamps();
        });

        Schema::create('email_outbox', function (Blueprint $table) {
            $table->id();
            $table->string('template');
            $table->string('recipient_email');
            $table->string('status')->default('queued');
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->index(['status', 'recipient_email']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('model_type')->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->string('action');
            $table->text('details')->nullable();
            $table->timestamps();
            $table->index(['model_type', 'model_id']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('email_outbox');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('external_calendar_events');
        Schema::dropIfExists('external_calendar_feeds');
        Schema::dropIfExists('admin_availability_blocks');
        Schema::dropIfExists('currency_configs');
    }
};
