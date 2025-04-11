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
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('version')->default('1.0.0');
            
            // Product Type
            $table->enum('type', [
                'software',
                'plugin',
                'theme',
                'service'
            ]);
            
            // Source Configuration
            $table->enum('source', [
                'custom',    // Your marketplace
                'envato',    // Envato marketplace
                'other'      // Other marketplaces
            ])->default('custom');
            
            $table->string('source_product_id')->nullable();
            $table->json('source_metadata')->nullable();
            
            // Requirements & Compatibility
            $table->json('requirements')->nullable();
            $table->json('compatibility')->nullable();
            $table->json('features')->nullable();
            
            // License Configuration
            $table->boolean('requires_domain_verification')->default(true);
            $table->boolean('requires_hardware_verification')->default(false);
            $table->integer('max_hardware_changes')->default(3);
            $table->integer('check_in_interval_days')->default(7);
            $table->integer('offline_grace_period_days')->default(3);
            
            // Version Control
            $table->string('current_version')->nullable('1.0.0');
            $table->json('version_history')->nullable();
            
            // Support Information
            $table->string('support_email')->nullable();
            $table->string('support_url')->nullable();
            $table->timestamp('support_ends_at')->nullable();
            
            // Additional Settings
            $table->json('settings')->nullable();
            $table->json('metadata')->nullable();
            
            // Status
            $table->string('status')->default('active');
            
            // Audit
            $table->string('created_by');
            $table->string('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['type', 'source']);
            $table->index('source_product_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
