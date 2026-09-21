<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registrar_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('domain_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 40);
            $table->string('operation', 60);
            $table->string('cltrid', 80)->unique();
            $table->string('svtrid', 120)->nullable();
            $table->string('status', 20);
            $table->string('provider_code', 20)->nullable();
            $table->text('provider_message')->nullable();
            $table->json('safe_request_metadata')->nullable();
            $table->json('provider_metadata')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'operation', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registrar_operations');
    }
};
