<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->boolean('is_admin')->default(false)->after('password'));
        Schema::table('domains', fn (Blueprint $table) => $table->index(['status', 'expires_at']));
        Schema::table('orders', fn (Blueprint $table) => $table->index(['type', 'status']));
        Schema::table('payments', fn (Blueprint $table) => $table->index(['status', 'paid_at']));
        Schema::table('transfers', fn (Blueprint $table) => $table->index(['status', 'updated_at']));
        Schema::table('ssl_certificates', fn (Blueprint $table) => $table->index(['status', 'expires_at']));
        Schema::table('registrar_operations', fn (Blueprint $table) => $table->index(['status', 'operation', 'started_at']));

        Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('action', 80);
            $table->string('resource_type', 80);
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->json('safe_metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['resource_type', 'resource_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
        Schema::table('registrar_operations', fn (Blueprint $table) => $table->dropIndex(['status', 'operation', 'started_at']));
        Schema::table('ssl_certificates', fn (Blueprint $table) => $table->dropIndex(['status', 'expires_at']));
        Schema::table('transfers', fn (Blueprint $table) => $table->dropIndex(['status', 'updated_at']));
        Schema::table('payments', fn (Blueprint $table) => $table->dropIndex(['status', 'paid_at']));
        Schema::table('orders', fn (Blueprint $table) => $table->dropIndex(['type', 'status']));
        Schema::table('domains', fn (Blueprint $table) => $table->dropIndex(['status', 'expires_at']));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_admin'));
    }
};
