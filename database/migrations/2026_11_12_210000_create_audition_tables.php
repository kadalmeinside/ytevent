<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('session_name', 100);
            $table->date('session_date')->nullable();
            $table->string('start_time', 10)->nullable();
            $table->string('end_time', 10)->nullable();
            $table->unsignedInteger('quota')->nullable();
            $table->unsignedInteger('occupied_slots')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'session_date']);
        });

        Schema::create('audition_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('stage_number')->default(1);
            $table->string('stage_name', 150);
            $table->date('stage_date');
            $table->string('start_time', 10)->nullable();
            $table->string('end_time', 10)->nullable();
            $table->string('venue_name', 150)->nullable();
            $table->unsignedInteger('target_quota')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_completed')->default(false);
            $table->text('instructions')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'stage_number']);
        });

        Schema::create('audition_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_session_id')->nullable()->constrained('event_sessions')->nullOnDelete();
            $table->string('registration_number', 64)->unique();
            $table->unsignedInteger('bib_number')->nullable();
            $table->string('full_name', 150);
            $table->string('nickname', 50)->nullable();
            $table->string('nik', 20);
            $table->date('birth_date');
            $table->string('gender', 16)->default('male');
            $table->string('preferred_position', 32)->nullable();
            $table->string('secondary_position', 32)->nullable();
            $table->string('dominant_foot', 16)->nullable();
            $table->unsignedSmallInteger('height_cm')->nullable();
            $table->unsignedSmallInteger('weight_kg')->nullable();
            $table->string('current_club_ssb', 150)->nullable();
            $table->text('playing_experience')->nullable();
            $table->string('jersey_size', 10)->nullable();
            $table->string('emergency_contact_name', 150);
            $table->string('emergency_contact_phone', 30);
            $table->string('emergency_contact_relation', 50)->nullable();
            $table->text('medical_notes')->nullable();
            $table->json('custom_answers')->nullable();
            $table->timestamp('liability_accepted_at')->nullable();
            $table->string('photo_path', 255)->nullable();
            $table->string('birth_certificate_path', 255)->nullable();
            $table->string('family_card_path', 255)->nullable();
            $table->string('additional_doc_path', 255)->nullable();
            $table->string('screening_status', 32)->default('submitted');
            $table->text('screening_notes')->nullable();
            $table->foreignId('screening_verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('screening_verified_at')->nullable();
            $table->string('screening_code', 64)->nullable()->unique();
            $table->string('stage_status', 32)->default('registered');
            $table->string('status', 32)->default('pending_payment');
            $table->timestamp('slot_reserved_at')->nullable();
            $table->timestamp('slot_expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['event_id', 'status']);
            $table->index(['event_id', 'screening_status']);
            $table->index(['user_id', 'event_id']);
            $table->index(['event_id', 'nik']);
            $table->index(['event_id', 'bib_number']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('team_id')->nullable()->change();
            $table->foreignId('audition_participant_id')->nullable()->after('team_id')->constrained('audition_participants')->cascadeOnDelete();
            $table->index(['audition_participant_id', 'status']);
        });

        Schema::create('audition_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audition_participant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('audition_stage_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scanned_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('check_in_at');
            $table->string('status', 20)->default('present');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['audition_participant_id', 'audition_stage_id'], 'participant_stage_unique');
        });

        Schema::create('scouting_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audition_participant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('audition_stage_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('scout_id')->constrained('users')->cascadeOnDelete();
            $table->string('quick_grade', 4); // A, B, C
            $table->unsignedTinyInteger('rating_stars')->default(3);
            $table->json('tags')->nullable();
            $table->text('coach_notes')->nullable();
            $table->boolean('is_locked')->default(false);
            $table->timestamps();

            $table->unique(['audition_participant_id', 'audition_stage_id', 'scout_id'], 'participant_stage_scout_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scouting_evaluations');
        Schema::dropIfExists('audition_attendances');

        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['audition_participant_id']);
            $table->dropIndex(['audition_participant_id', 'status']);
            $table->dropColumn('audition_participant_id');
            $table->unsignedBigInteger('team_id')->nullable(false)->change();
        });

        Schema::dropIfExists('audition_participants');
        Schema::dropIfExists('audition_stages');
        Schema::dropIfExists('event_sessions');
    }
};

