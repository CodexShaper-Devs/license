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
            $table->foreignUuid('license_id')->constrained();
            
            // Activation Details
            $table->string('activation_token')->unique();
            $table->enum('type', ['domain', 'machine', 'user']);
            
            // Device Information
            $table->string('device_identifier')->nullable();
            $table->string('device_name')->nullable();
            $table->json('hardware_hash')->nullable();
            $table->json('system_info')->nullable();
            
            // Network Information
            $table->string('ip_address')->nullable();
            $table->string('mac_address')->nullable();
            
            // Status
            $table->boolean('is_active')->default(true);
            $table->timestamp('activated_at');
            $table->timestamp('last_check_in')->nullable();
            $table->timestamp('next_check_in')->nullable();
            $table->integer('failed_checks')->default(0);
            
            // Deactivation
            $table->timestamp('deactivated_at')->nullable();
            $table->string('deactivated_by')->nullable();
            $table->string('deactivation_reason')->nullable();
            
            // Metadata
            $table->json('metadata')->nullable();
            $table->string('user_agent')->nullable();
            
            // Audit
            $table->string('created_by');
            $table->string('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['license_id', 'is_active']);
            $table->index('device_identifier');
            $table->index('next_check_in');
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
