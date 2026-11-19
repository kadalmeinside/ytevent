<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_logs', function (Blueprint $table): void {
            $table->string('idempotency_key', 64)->nullable()->unique()->after('provider');
            $table->timestamp('processed_at')->nullable()->after('payload');
        });
    }

    public function down(): void
    {
        Schema::table('webhook_logs', function (Blueprint $table): void {
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn(['idempotency_key', 'processed_at']);
        });
    }
};
