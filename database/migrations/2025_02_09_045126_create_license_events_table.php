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
        Schema::create('license_events', function (Blueprint $table) {
            // Primary Key and Relations
            $table->uuid('id')->primary();
            $table->foreignUuid('license_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('activation_id')->nullable()->constrained('license_activations')->onDelete('set null');
            $table->foreignUuid('domain_id')->nullable()->constrained('license_domains')->onDelete('set null');
            
            // Event Classification
            $table->enum('event_type', [
                // License Events
                'license.created',
                'license.activated',
                'license.deactivated',
                'license.renewed',
                'license.expired',
                'license.suspended',
                'license.restored',
                // Validation Events
                'validation.success',
                'validation.failed',
                'validation.domain',
                'validation.hardware',
                // Domain Events
                'domain.added',
                'domain.removed',
                'domain.validated',
                'domain.verification_failed',
                // Security Events
                'security.key_rotation',
                'security.suspicious_activity',
                'security.blocked_ip',
                // System Events
                'system.check_in',
                'system.grace_period_started',
                'system.grace_period_ended',
                // Audit Events
                'audit.settings_changed',
                'audit.access_attempt'
            ]);
            
            // Event Details
            $table->json('event_data')->nullable()->comment('Primary event data');
            $table->json('previous_state')->nullable()->comment('State before the event');
            $table->json('current_state')->nullable()->comment('State after the event');
            $table->json('changes')->nullable()->comment('Specific changes made');
            
            // Request Context
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->json('request_headers')->nullable();
            $table->json('request_metadata')->nullable()->comment('Additional request context');
            
            // Geographic Information
            $table->string('country_code', 2)->nullable();
            $table->string('country_name')->nullable();
            $table->string('city')->nullable();
            $table->string('timezone')->nullable();
            
            // Result Information
            $table->enum('severity', ['info', 'warning', 'error', 'critical'])->default('info');
            $table->boolean('success')->default(true);
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('attempt_count')->default(1);
            
            // Performance Metrics
            $table->integer('response_time_ms')->nullable()->comment('Response time in milliseconds');
            $table->json('performance_metrics')->nullable()->comment('Detailed performance data');
            
            // Security Context
            $table->json('security_context')->nullable()->comment('Security-related metadata');
            $table->boolean('is_suspicious')->default(false);
            $table->text('security_notes')->nullable();
            
            // Audit Trail
            $table->string('created_by')->default('maab16');
            $table->timestamp('created_at')->default('2025-02-09 14:27:02');
            $table->string('source')->default('system')->comment('Origin of the event');
            $table->string('environment')->default('production');
            
            // Indexes for Common Queries
            $table->index(['license_id', 'event_type']);
            $table->index(['created_at', 'event_type']);
            $table->index(['ip_address', 'created_at']);
            $table->index(['success', 'event_type']);
            $table->index('severity');
            
            // Composite Indexes for Complex Queries
            $table->index(['license_id', 'event_type', 'created_at']);
            $table->index(['success', 'severity', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('license_events');
    }
};
