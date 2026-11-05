<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 64)->unique();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();

            $table->decimal('amount', 14, 2);
            $table->decimal('gateway_fee', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2);
            $table->string('method', 32)->nullable()->index(); // midtrans, xendit, manual
            $table->string('payment_channel', 64)->nullable();

            $table->string('external_reference_id', 128)->nullable()->index();
            $table->string('snap_token', 255)->nullable();
            $table->string('payment_url', 500)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('webhook_payload')->nullable();

            $table->string('sender_bank_name', 100)->nullable();
            $table->string('sender_account_holder', 120)->nullable();
            $table->string('sender_account_number', 64)->nullable();
            $table->date('transfer_date')->nullable();
            $table->string('proof_file_path', 255)->nullable();

            // unpaid, waiting_verification, paid, rejected, expired, refund_required, refunded
            $table->string('status', 32)->default('unpaid');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('verified_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'expires_at']);
            $table->index(['event_id', 'status']);
            $table->index(['team_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

