<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('slug', 180)->unique();
            $table->string('banner_path', 255)->nullable();
            $table->text('short_description')->nullable();
            $table->string('venue_name', 160);
            $table->string('city', 120)->index();

            $table->string('format', 32)->index(); // cup, league, group_knockout, festival
            $table->string('age_category', 32)->index(); // U-10, U-12, U-15, U-17, Senior
            $table->date('min_birth_date')->nullable();
            $table->date('max_birth_date')->nullable();
            $table->unsignedSmallInteger('min_players_per_team')->default(14);
            $table->unsignedSmallInteger('max_players_per_team')->default(25);
            $table->unsignedSmallInteger('max_officials_per_team')->default(5);
            $table->json('required_documents');

            $table->unsignedInteger('max_teams');
            $table->decimal('registration_fee', 14, 2)->default(0);
            $table->string('prize_info', 255)->nullable();
            $table->boolean('lock_roster_until_paid')->default(true);
            $table->unsignedSmallInteger('payment_deadline_hours')->default(24);
            $table->unsignedSmallInteger('reupload_grace_hours')->default(6);

            $table->timestamp('registration_open_at')->nullable();
            $table->timestamp('registration_close_at')->nullable();
            $table->date('match_start_date')->nullable();
            $table->date('match_end_date')->nullable();
            $table->string('regulation_pdf_path', 255)->nullable();
            $table->string('contact_person', 120)->nullable();
            $table->string('contact_whatsapp', 30)->nullable();
            $table->string('status', 32)->default('draft')->index(); // draft, open, quota_full, closed, ongoing, completed

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};

