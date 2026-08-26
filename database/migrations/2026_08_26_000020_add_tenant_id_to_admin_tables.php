<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('establishments', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->dropUnique(['slug']);
            $table->unique(['tenant_id', 'slug']);
        });

        Schema::table('amenity_categories', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->dropUnique(['slug']);
            $table->unique(['tenant_id', 'slug']);
        });

        Schema::table('amenities', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->dropUnique(['slug']);
            $table->unique(['tenant_id', 'slug']);
        });

        Schema::table('site_reviews', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            // A future tenant will want its own 'site_name' etc., so the key can no longer be globally unique.
            $table->dropUnique(['key']);
            $table->unique(['tenant_id', 'key']);
        });

        Schema::table('users', function (Blueprint $table) {
            // Nullable: customers book across every tenant and don't belong to one.
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        // Give every pre-existing row a home so on-premise installs keep working unchanged.
        $tenantId = DB::table('tenants')->insertGetId([
            'name' => 'Default',
            'slug' => 'default',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('establishments')->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);
        DB::table('amenity_categories')->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);
        DB::table('amenities')->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);
        DB::table('site_reviews')->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);
        DB::table('settings')->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);
        DB::table('users')->where('role', '!=', 'customer')->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'key']);
            $table->unique('key');
            $table->dropConstrainedForeignId('tenant_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });

        Schema::table('site_reviews', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });

        Schema::table('amenities', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'slug']);
            $table->unique('slug');
            $table->dropConstrainedForeignId('tenant_id');
        });

        Schema::table('amenity_categories', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'slug']);
            $table->unique('slug');
            $table->dropConstrainedForeignId('tenant_id');
        });

        Schema::table('establishments', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'slug']);
            $table->unique('slug');
            $table->dropConstrainedForeignId('tenant_id');
        });
    }
};
