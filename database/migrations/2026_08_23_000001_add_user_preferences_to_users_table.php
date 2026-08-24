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
        Schema::table('users', function (Blueprint $table) {
            $table->string('locale')->default('fr')->after('email');
            $table->boolean('email_booking_updates')->default(true);
            $table->boolean('email_marketing')->default(false);
            $table->boolean('email_newsletter')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['locale', 'email_booking_updates', 'email_marketing', 'email_newsletter']);
        });
    }
};
