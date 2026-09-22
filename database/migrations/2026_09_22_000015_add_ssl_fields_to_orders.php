<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('ssl_certificate_id')->nullable()->constrained('ssl_certificates')->nullOnDelete();
            $table->json('ssl_data')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropConstrainedForeignId('ssl_certificate_id')->dropColumn('ssl_data'));
    }
};
