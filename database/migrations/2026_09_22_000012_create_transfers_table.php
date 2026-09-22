<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('domain_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('domain', 253);
            $table->string('tld', 63);
            $table->string('provider', 40);
            $table->string('direction', 3);
            $table->string('status', 30);
            $table->string('provider_status')->nullable();
            $table->string('provider_transfer_id')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('provider_synced_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index(['domain', 'direction']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
