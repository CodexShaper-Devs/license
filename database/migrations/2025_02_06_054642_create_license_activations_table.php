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
        Schema::create('license_activations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('license_id')->constrained('licenses');
            
            // Domain and Hardware Info
            $table->string('domain');
            $table->string('hardware_id');
            $table->string('instance_identifier');
            $table->ipAddress('ip_address');
            
            // Version and Status
            $table->string('product_version');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_check_in');
            
            // Additional Data
            $table->json('metadata');
            
            // Audit
            $table->string('created_by');
            $table->string('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Unique Constraints
            $table->unique(['license_id', 'domain']);
            $table->unique(['license_id', 'hardware_id']);
            $table->unique('instance_identifier');
            
            // Indexes
            $table->index('is_active');
            $table->index('last_check_in');

            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('license_activations');
    }
};
