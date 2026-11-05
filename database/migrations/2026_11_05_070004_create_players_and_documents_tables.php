<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();

            $table->string('full_name', 100);
            $table->string('jersey_name', 50)->nullable();
            $table->unsignedTinyInteger('jersey_number');
            $table->string('position', 8); // GK, DF, MF, FW
            $table->string('dominant_foot', 16)->default('Right'); // Right, Left, Both

            $table->string('birth_place', 100)->nullable();
            $table->date('birth_date');
            $table->string('nik', 16);
            $table->string('nisn', 20)->nullable();
            $table->unsignedSmallInteger('height_cm')->nullable();
            $table->unsignedSmallInteger('weight_kg')->nullable();
            $table->string('photo_path', 255)->nullable();

            // draft, submitted, passed, rejected
            $table->string('screening_status', 32)->default('draft')->index();
            $table->boolean('is_locked')->default(false);
            $table->text('rejection_reason')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->string('accreditation_code', 64)->nullable()->unique();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['event_id', 'nik']);
            $table->unique(['team_id', 'jersey_number']);
            $table->index(['team_id', 'screening_status']);
        });

        Schema::create('player_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            // birth_certificate, family_card, student_card_kia, report_card_diploma, national_id_ktp, passport, domicile_letter
            $table->string('document_type', 48);
            $table->string('document_number', 100)->nullable();
            $table->string('file_path', 255);
            $table->string('original_name', 255)->nullable();
            $table->unsignedInteger('file_size')->default(0);
            $table->string('mime_type', 64);
            // pending, valid, invalid
            $table->string('validation_status', 24)->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['player_id', 'document_type']);
        });

        Schema::create('team_officials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('full_name', 100);
            $table->string('role', 48); // Head Coach, Assistant Coach, Goalkeeper Coach, Physiotherapist, Team Manager, Kitman
            $table->string('license_number', 80)->nullable();
            $table->string('license_file_path', 255)->nullable();
            $table->string('photo_path', 255)->nullable();
            $table->string('phone', 25)->nullable();
            $table->string('screening_status', 32)->default('draft');
            $table->text('verification_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'role']);
        });

        Schema::create('screening_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['player_id', 'created_at']);
        });

        Schema::create('email_notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->string('mailable_type', 120);
            $table->string('recipient_email', 160)->index();
            $table->string('status', 24)->default('queued')->index(); // queued, sent, failed
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_notification_logs');
        Schema::dropIfExists('screening_logs');
        Schema::dropIfExists('team_officials');
        Schema::dropIfExists('player_documents');
        Schema::dropIfExists('players');
    }
};

