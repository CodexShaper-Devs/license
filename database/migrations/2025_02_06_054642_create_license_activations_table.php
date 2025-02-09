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
            $table->string('device_identifier');
            $table->string('device_name');
            $table->string('hardware_hash');
            $table->string('domain');
            $table->string('ip_address');
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('activated_at');
            $table->string('activated_by');
            $table->timestamp('last_check_in');
            $table->timestamp('next_check_in');
            $table->timestamp('deactivated_at')->nullable();
            $table->string('deactivated_by')->nullable();
            $table->timestamps();

            $table->unique(['license_id', 'device_identifier']);
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
