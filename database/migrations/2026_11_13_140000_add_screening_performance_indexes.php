<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audition_participants', function (Blueprint $table) {
            $table->index(['event_id', 'screening_status', 'id'], 'audition_participants_event_screening_id_idx');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['auditable_type', 'auditable_id', 'id'], 'audit_logs_auditable_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('audition_participants', function (Blueprint $table) {
            $table->dropIndex('audition_participants_event_screening_id_idx');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_logs_auditable_id_idx');
        });
    }
};

