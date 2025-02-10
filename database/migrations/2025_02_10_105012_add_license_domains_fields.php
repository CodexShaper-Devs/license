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
        Schema::table('license_domains', function (Blueprint $table) {
            $table->timestamp('activated_at')->nullable()->after('is_active');
            $table->timestamp('verified_at')->nullable()->after('activated_at');
            $table->timestamp('last_checked_at')->nullable()->after('verified_at');
            $table->string('verification_hash')->nullable()->after('domain');
            $table->string('verification_method')->nullable()->after('verification_hash');
            $table->timestamp('deactivated_at')->nullable()->after('last_checked_at');
            $table->string('deactivated_by')->nullable()->after('deactivated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('license_domains', function (Blueprint $table) {
            $table->dropColumn([
                'verified_at',
                'activated_at',
                'deactivated_at',
                'deactivated_by',
                'last_checked_at',
                'verification_hash',
                'verification_method'
            ]);
        });
    }
};
