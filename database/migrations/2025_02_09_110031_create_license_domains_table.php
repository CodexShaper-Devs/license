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
        Schema::create('license_domains', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('license_id')->constrained('licenses');
            
            // Domain Information
            $table->string('domain')->unique();
            $table->boolean('is_primary')->default(false);
            $table->boolean('allow_subdomains')->default(false);
            $table->integer('max_subdomains')->default(0);
            $table->json('allowed_subdomains')->nullable();
            
            // Status
            $table->boolean('is_active')->default(true);
            $table->timestamp('validated_at')->nullable();
            
            // Audit
            $table->string('created_by');
            $table->string('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('domain');
            $table->index('is_active');
            $table->index(['license_id', 'is_primary']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('license_domains');
    }
};
