<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->boolean('activation_pending')->default(false)->after('is_admin'));
        Schema::table('domains', function (Blueprint $table): void {
            $table->string('acquisition_source', 30)->default('platform_checkout')->after('provider');
            $table->foreignId('imported_by_admin_id')->nullable()->after('acquisition_source')->constrained('users')->nullOnDelete();
            $table->timestamp('imported_at')->nullable()->after('imported_by_admin_id');
            $table->text('internal_note')->nullable()->after('imported_at');
            $table->index(['acquisition_source', 'imported_at']);
        });
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table): void {
            $table->dropForeign(['imported_by_admin_id']);
            $table->dropIndex(['acquisition_source', 'imported_at']);
            $table->dropColumn(['acquisition_source', 'imported_by_admin_id', 'imported_at', 'internal_note']);
        });
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('activation_pending'));
    }
};
