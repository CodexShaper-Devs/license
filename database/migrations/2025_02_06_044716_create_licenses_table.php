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
            // Primary Identification Columns
            $table->uuid('id')->primary();          // Primary key for the license
            $table->uuid('uuid');                   // Public UUID for external API references

            // License Content & Security
            $table->string('key')->unique();          // Encrypted license data (large text to accommodate encryption)
            $table->string('signature')->nullable(); // Digital signature for license verification

            // License Type & Configuration
            $table->enum('type', [                  // Type of license
                'subscription',                      // Recurring payment with expiry
                'onetime'                           // One-time payment
            ]);

            // Relationships
            $table->uuid('product_id');             // Related product identifier
            $table->unsignedBigInteger('user_id');  // License owner/purchaser

            // Usage Limits
            $table->integer('seats')->default(1);    // Number of allowed concurrent users/installations

            // Features & Customization
            $table->json('features')->nullable();    // Additional features enabled for this license
                                                    // Example: ["feature1", "feature2"]

            // Validity Period
            $table->timestamp('valid_from');         // License activation date
            $table->timestamp('valid_until')         // License expiration date
                  ->nullable();                      // Null means perpetual license

            // Restrictions & Metadata
            $table->json('restrictions'); // License usage restrictions
                                                     // Example: {"domain": "example.com"}
            $table->json('metadata')->nullable();     // Additional custom data
                                                     // Example: {"customer_name": "John Doe"}

            // Source Information
            $table->string('source')                 // Where the license was purchased
                  ->default('custom');               // Default is custom/direct purchase
            $table->string('source_purchase_code')   // External purchase reference
                  ->nullable();                      // Optional purchase code from marketplace

            // Encryption & Authentication
            $table->string('encryption_key_id');     // ID of the key used for encryption
            $table->string('auth_key_id');          // ID of the key used for authentication

            // Status
            $table->string('status')                // Current license status
                  ->default('active');              // Default is active

            // Audit Trail
            $table->string('created_by');           // User who created the license
            $table->string('updated_by')            // User who last modified the license
                  ->nullable();
            
            // Add indexes for common queries
            $table->index('status');
            $table->index('type');
            $table->index(['encryption_key_id', 'auth_key_id']);

            // Timestamps
            $table->timestamps();                   // created_at and updated_at columns
            $table->softDeletes();                  // deleted_at column for soft deletes

            // Indexes for Performance
            $table->index(['source', 'source_purchase_code', 'type', 'status']); // Optimize source-based queries
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
