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
            $table->string('key')->unique();
            $table->foreignId('user_id')->constrained();
            $table->uuid('product_id');
            $table->string('type');
            $table->string('status');
            $table->integer('seats');
            $table->timestamp('valid_from');
            $table->timestamp('valid_until');
            $table->json('features')->nullable();
            $table->json('restrictions')->nullable();
            $table->json('metadata')->nullable();
            $table->json('settings')->nullable();
            $table->string('signature')->nullable();
            $table->string('encryption_key_id');
            $table->string('auth_key_id');
            $table->string('created_by');
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products');
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
