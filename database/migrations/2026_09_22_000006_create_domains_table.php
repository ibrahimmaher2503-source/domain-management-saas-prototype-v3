<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 253)->unique();
            $table->string('tld', 63);
            $table->string('provider', 40);
            $table->string('status', 30);
            $table->date('registered_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->boolean('auto_renew')->nullable();
            $table->boolean('transfer_locked')->nullable();
            $table->string('privacy_status')->nullable();
            $table->json('nameservers');
            $table->string('provider_status')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domains');
    }
};
