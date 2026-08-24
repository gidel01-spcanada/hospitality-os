<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rate_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->decimal('nightly_rate_xof', 12, 2)->default(0);
            $table->decimal('nightly_rate_eur', 12, 2)->default(0);
            $table->integer('minimum_stay')->default(1);
            $table->string('rule_type')->default('standard');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['property_id', 'effective_from']);
        });

        Schema::create('reservation_guests', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('country')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guest_id')->nullable()->constrained('reservation_guests')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reservation_ref')->unique();
            $table->string('status')->default('pending');
            $table->date('check_in');
            $table->date('check_out');
            $table->integer('adults')->default(1);
            $table->integer('children')->default(0);
            $table->integer('infants')->default(0);
            $table->string('currency', 3)->default('XOF');
            $table->string('email')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('fees', 12, 2)->default(0);
            $table->decimal('taxes', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('source')->default('website');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['status', 'check_in']);
            $table->index(['property_id', 'check_in', 'check_out']);
            $table->index('reservation_ref');
            $table->index('email');
        });

        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('provider_reference')->nullable();
            $table->string('currency', 3)->default('XOF');
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('status')->default('pending');
            $table->string('idempotency_key')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->index(['provider', 'status']);
            $table->index('idempotency_key');
        });

        Schema::create('reservation_price_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('currency', 3)->default('XOF');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_price_lines');
        Schema::dropIfExists('payment_attempts');
        Schema::dropIfExists('reservations');
        Schema::dropIfExists('reservation_guests');
        Schema::dropIfExists('rate_rules');
    }
};
