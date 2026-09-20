<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cleaning_schedule_shares', function (Blueprint $table) {
            $table->string('assignee_name')->nullable()->after('property_ids');
        });
    }

    public function down(): void
    {
        Schema::table('cleaning_schedule_shares', function (Blueprint $table) {
            $table->dropColumn('assignee_name');
        });
    }
};