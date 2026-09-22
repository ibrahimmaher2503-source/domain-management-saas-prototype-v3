<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ssl_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 40);
            $table->string('provider_order_id')->nullable();
            $table->string('product_key', 80);
            $table->string('provider_product', 80);
            $table->string('status', 30);
            $table->string('provider_status')->nullable();
            $table->string('validation_type', 20)->nullable();
            $table->string('approver_email')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->timestamp('provider_synced_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ssl_certificates');
    }
};
