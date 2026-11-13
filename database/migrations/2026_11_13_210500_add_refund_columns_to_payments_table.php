<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('refund_amount', 14, 2)->nullable()->after('rejection_reason');
            $table->string('refund_reference', 120)->nullable()->after('refund_amount');
            $table->string('refund_method', 64)->nullable()->after('refund_reference');
            $table->text('refund_notes')->nullable()->after('refund_method');
            $table->timestamp('refunded_at')->nullable()->after('refund_notes');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn([
                'refund_amount',
                'refund_reference',
                'refund_method',
                'refund_notes',
                'refunded_at',
            ]);
        });
    }
};

