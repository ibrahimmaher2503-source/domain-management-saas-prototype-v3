<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->json('provider_contact_ids')->nullable();
            $table->text('domain_password')->nullable();
            $table->text('provisioning_failure_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['provider_contact_ids', 'domain_password', 'provisioning_failure_reason']);
        });
    }
};
