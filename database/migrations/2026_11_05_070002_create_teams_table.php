<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('name', 100);
            $table->string('slug', 140)->index();
            $table->string('logo_path', 255);
            $table->string('origin_association', 140);
            $table->text('secretariat_address')->nullable();

            $table->string('manager_name', 100);
            $table->string('manager_whatsapp', 25);
            $table->string('manager_email', 160);
            $table->string('head_coach_name', 100);
            $table->string('head_coach_license', 80)->nullable();
            $table->timestamp('terms_accepted_at');

            // pending_payment, active, locked_roster, expired, disqualified
            $table->string('status', 32)->default('pending_payment');
            $table->timestamp('slot_reserved_at')->nullable();
            $table->timestamp('slot_expires_at')->nullable();

            // not_started, in_review, partially_rejected, all_passed
            $table->string('screening_status', 32)->default('not_started')->index();
            $table->text('internal_notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['event_id', 'name']);
            $table->index(['event_id', 'status']);
            $table->index(['user_id', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};

