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
        Schema::create('license_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            
            // Plan Configuration
            $table->enum('billing_cycle', ['yearly', 'lifetime'])->default('yearly');
            $table->integer('base_seats')->default(1);
            $table->integer('max_seats')->default(1);
            $table->boolean('allow_seat_upgrade')->default(true);
            $table->decimal('price_per_seat', 10, 2);
            $table->decimal('renewal_per_seat', 10, 2);

            // License Type & Configuration
            $table->enum('type', [
                'subscription',      // Renewable subscription
                'lifetime',         // One-time perpetual
                'trial'            // Trial license
            ])->default('subscription');

            $table->integer('duration_months')->default(12);
            
            // Domain Configuration
            $table->boolean('allow_subdomains')->default(false);
            $table->integer('subdomains_per_seat')->default(0);
            $table->json('allowed_domain_patterns')->nullable();
            $table->boolean('allow_local_domains')->default(true);
            $table->json('local_domain_patterns')->nullable();
            
            // Feature Configuration
            $table->json('features')->nullable();
            $table->json('restrictions')->nullable();
            
            // Trial Configuration
            $table->boolean('has_trial')->default(false);
            $table->integer('trial_days')->default(0);
            
            // Grace Period Configuration
            $table->integer('grace_period_days')->default(0);
            
            // Status and Metadata
            $table->string('status')->default('active');
            $table->json('metadata')->nullable();
            $table->json('settings')->nullable();
            
            // Audit
            $table->string('created_by');
            $table->string('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('billing_cycle');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('license_plans');
    }
};
