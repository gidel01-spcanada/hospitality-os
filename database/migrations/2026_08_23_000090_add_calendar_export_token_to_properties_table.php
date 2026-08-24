<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('properties', 'calendar_export_token')) {
            Schema::table('properties', function (Blueprint $table) {
                $table->string('calendar_export_token', 64)->nullable()->unique()->after('metadata');
            });
        }

        DB::table('properties')->whereNull('calendar_export_token')->get()->each(function (object $property): void {
            DB::table('properties')->where('id', $property->id)->update([
                'calendar_export_token' => bin2hex(random_bytes(24)),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropUnique(['calendar_export_token']);
            $table->dropColumn('calendar_export_token');
        });
    }
};