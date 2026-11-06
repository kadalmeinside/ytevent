<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_notification_logs', function (Blueprint $table) {
            $table->string('subject', 255)->nullable()->after('recipient_email');
            // Mailable terserialisasi (SerializesModels) untuk mekanisme Kirim Ulang (Retry)
            $table->longText('payload')->nullable()->after('subject');
            $table->unsignedSmallInteger('attempts')->default(0)->after('status');
            $table->timestamp('last_attempted_at')->nullable()->after('attempts');
        });
    }

    public function down(): void
    {
        Schema::table('email_notification_logs', function (Blueprint $table) {
            $table->dropColumn(['subject', 'payload', 'attempts', 'last_attempted_at']);
        });
    }
};
