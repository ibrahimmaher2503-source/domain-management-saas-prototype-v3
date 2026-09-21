<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 40);
            $table->string('status', 20);
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->string('provider_intention_id')->nullable();
            $table->unsignedBigInteger('provider_order_id')->nullable();
            $table->string('provider_transaction_id')->nullable();
            $table->string('provider_reference');
            $table->string('provider_status')->nullable();
            $table->text('checkout_reference')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->json('provider_metadata')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_reference']);
            $table->unique(['provider', 'provider_transaction_id']);
            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
