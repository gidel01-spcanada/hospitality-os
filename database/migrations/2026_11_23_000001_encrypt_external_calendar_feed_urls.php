<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_calendar_feeds', function (Blueprint $table): void {
            $table->longText('encrypted_url')->nullable();
        });

        DB::table('external_calendar_feeds')->select(['id', 'url'])->orderBy('id')->each(function (object $feed): void {
            DB::table('external_calendar_feeds')->where('id', $feed->id)->update([
                'encrypted_url' => Crypt::encryptString((string) $feed->url),
                'url' => '',
            ]);
        });
    }

    public function down(): void
    {
        DB::table('external_calendar_feeds')->select(['id', 'encrypted_url'])->orderBy('id')->each(function (object $feed): void {
            if ($feed->encrypted_url !== null) {
                DB::table('external_calendar_feeds')->where('id', $feed->id)->update([
                    'url' => Crypt::decryptString($feed->encrypted_url),
                ]);
            }
        });

        Schema::table('external_calendar_feeds', function (Blueprint $table): void {
            $table->dropColumn('encrypted_url');
        });
    }
};
