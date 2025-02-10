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
        Schema::create('licenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('uuid')->unique();
            
            // License Key & Security
            $table->text('key');
            $table->text('signature');
            $table->string('encryption_key_id');
            $table->string('auth_key_id');
            $table->json('security_metadata')->nullable();
            
            // Relationships
            $table->uuid('product_id');
            $table->uuid('plan_id')->nullable();
            $table->foreignId('user_id')->constrained();
            
            // License Type & Configuration
            $table->enum('type', [
                'subscription',      // Renewable subscription
                'lifetime',         // One-time perpetual
                'trial'            // Trial license
            ]);
            
            // Seat Management
            $table->integer('purchased_seats')->default(1);
            $table->integer('activated_seats')->default(0);
            $table->json('seat_allocation')->nullable();
            
            // Source Information
            $table->string('source')->default('custom');
            $table->string('source_purchase_code')->nullable();
            $table->json('source_metadata')->nullable();
            
            // Features & Restrictions
            $table->json('features')->nullable();
            $table->json('restrictions')->nullable();
            
            // Validity Period
            $table->timestamp('valid_from');
            $table->timestamp('valid_until')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('grace_period_ends_at')->nullable();
            
            // Check-in Configuration
            $table->timestamp('last_check_in')->nullable();
            $table->timestamp('next_check_in')->nullable();
            $table->integer('failed_checks')->default(0);
            $table->integer('max_failed_checks')->default(3);
            
            // Hardware Verification
            $table->boolean('hardware_verification_enabled')->default(false);
            $table->integer('hardware_changes_count')->default(0);
            $table->json('hardware_history')->nullable();
            
            // Status Management
            $table->enum('status', [
                'pending',          // Awaiting activation
                'active',           // License is active
                'suspended',        // Temporarily suspended
                'expired',          // License has expired
                'cancelled',        // Cancelled by user/admin
                'trial',            // In trial period
                'grace_period'      // In grace period
            ])->default('pending');
            
            // Renewal Configuration
            $table->boolean('auto_renew')->default(false);
            $table->timestamp('renewal_reminder_sent_at')->nullable();
            
            // Additional Data
            $table->json('metadata')->nullable();
            
            // Audit Trail
            $table->string('created_by');
            $table->string('updated_by')->nullable();
            $table->string('suspended_by')->nullable();
            $table->text('suspension_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['source', 'source_purchase_code']);
            $table->index(['status', 'type']);
            $table->index('valid_until');
            $table->index('next_check_in');
            
            // Foreign Keys
            $table->foreign('product_id')->references('id')->on('products');
            $table->foreign('plan_id')->references('id')->on('license_plans');
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('licenses');
    }
};
