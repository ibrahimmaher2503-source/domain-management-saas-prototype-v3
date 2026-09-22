<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dns_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('provider', 30);
            $table->string('provider_zone_id', 64)->nullable();
            $table->boolean('creation_attempted')->default(false);
            $table->string('status', 30)->default('pending');
            $table->string('provider_status', 30)->nullable();
            $table->json('assigned_nameservers')->nullable();
            $table->timestamp('provider_synced_at')->nullable();
            $table->timestamps();
        });
        Schema::create('dns_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dns_zone_id')->constrained()->cascadeOnDelete();
            $table->string('operation', 30);
            $table->string('record_id', 64)->nullable();
            $table->string('record_type', 10)->nullable();
            $table->string('record_name')->nullable();
            $table->string('status', 20);
            $table->json('request_data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dns_operations');
        Schema::dropIfExists('dns_zones');
    }
};
