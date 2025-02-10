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
            $table->foreignUuid('license_id')->constrained();
            $table->foreignUuid('activation_id')->constrained('license_activations')->onDelete('cascade');
            // Domain Configuration
            $table->string('domain');
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('allow_subdomains')->default(false);
            $table->integer('max_subdomains')->default(0);
            $table->json('allowed_subdomains')->nullable();
            
            // Validation
            $table->string('validation_token')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->string('validation_method')->nullable();
            
            // DNS Records
            $table->string('dns_record_type')->nullable();
            $table->string('dns_record_value')->nullable();
            $table->timestamp('dns_validated_at')->nullable();
            
            // Health Check
            $table->timestamp('last_check_in')->nullable();
            $table->timestamp('next_check_in')->nullable();
            $table->integer('failed_checks')->default(0);
            
            // Audit
            $table->string('created_by');
            $table->string('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('domain');
            $table->index(['license_id', 'is_active']);
            $table->unique(['license_id', 'domain']);
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
