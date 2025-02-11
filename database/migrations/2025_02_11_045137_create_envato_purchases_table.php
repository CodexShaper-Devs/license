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
        Schema::create('envato_purchases', function (Blueprint $table) {
            $table->id();
            $table->string('purchase_code')->unique();
            $table->string('buyer_username');
            $table->string('buyer_email')->nullable();
            $table->string('item_id');
            $table->string('item_name');
            $table->timestamp('purchase_date');
            $table->string('license_type');
            $table->timestamp('support_expiry');
            $table->integer('purchase_count')->default(1);
            $table->timestamp('last_verified_at');
            $table->string('last_verified_by');
            $table->json('metadata')->nullable();
            $table->string('created_by');
            $table->string('updated_by');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['purchase_code', 'item_id']);
            $table->index('buyer_username');
            $table->index('support_expiry');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('envato_purchases');
    }
};
