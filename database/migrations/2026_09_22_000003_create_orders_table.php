<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('status', 30);
            $table->string('domain', 253);
            $table->string('tld', 63);
            $table->unsignedSmallInteger('registration_period');
            $table->string('provider', 40);
            $table->decimal('provider_cost', 12, 2);
            $table->decimal('customer_price', 12, 2);
            $table->string('currency', 3);
            $table->boolean('premium')->default(false);
            $table->string('tmch_lookup_key')->nullable();
            $table->json('registration_data');
            $table->json('nameservers');
            $table->timestamps();
            $table->index(['user_id', 'domain', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
