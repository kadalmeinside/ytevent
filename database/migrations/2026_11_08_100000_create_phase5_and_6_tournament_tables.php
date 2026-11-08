<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fase 6.2: Warna Kostum Tim (Home, Away & Goalkeeper Kit Colors)
        Schema::table('teams', function (Blueprint $table) {
            if (! Schema::hasColumn('teams', 'home_jersey_color')) {
                $table->string('home_jersey_color', 60)->nullable()->after('head_coach_license');
            }
            if (! Schema::hasColumn('teams', 'away_jersey_color')) {
                $table->string('away_jersey_color', 60)->nullable()->after('home_jersey_color');
            }
            if (! Schema::hasColumn('teams', 'gk_jersey_color')) {
                $table->string('gk_jersey_color', 60)->nullable()->after('away_jersey_color');
            }
            if (! Schema::hasColumn('teams', 'achievement_title')) {
                $table->string('achievement_title', 100)->nullable()->after('gk_jersey_color');
            }
        });

        // Fase 5.1: Pembagian Grup Kompetisi (Event Groups)
        Schema::create('event_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('name', 80); // misal: Grup A, Grup B
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->timestamps();
        });

        // Fase 5.1: Anggota Tim dalam Grup & Klasemen (Event Group Teams)
        Schema::create('event_group_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_group_id')->constrained('event_groups')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->unsignedSmallInteger('played')->default(0);
            $table->unsignedSmallInteger('won')->default(0);
            $table->unsignedSmallInteger('drawn')->default(0);
            $table->unsignedSmallInteger('lost')->default(0);
            $table->unsignedSmallInteger('goals_for')->default(0);
            $table->unsignedSmallInteger('goals_against')->default(0);
            $table->smallInteger('goal_difference')->default(0);
            $table->unsignedSmallInteger('points')->default(0);
            $table->unsignedSmallInteger('yellow_cards')->default(0);
            $table->unsignedSmallInteger('red_cards')->default(0);
            $table->unsignedSmallInteger('manual_rank_override')->nullable();
            $table->timestamps();

            $table->unique(['event_group_id', 'team_id']);
        });

        // Fase 5.1: Jadwal & Hasil Pertandingan (Tournament Matches)
        Schema::create('tournament_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('event_group_id')->nullable()->constrained('event_groups')->nullOnDelete();
            $table->string('stage', 40)->default('group'); // group, round_of_16, quarter_final, semi_final, third_place, final
            $table->unsignedSmallInteger('match_number')->default(1);
            $table->foreignId('home_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('away_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->dateTime('kickoff_at')->nullable();
            $table->string('pitch_name', 80)->nullable(); // misal: Lapangan A
            $table->string('status', 30)->default('scheduled'); // scheduled, ongoing, completed, postponed
            $table->unsignedSmallInteger('home_score')->nullable();
            $table->unsignedSmallInteger('away_score')->nullable();
            $table->unsignedSmallInteger('home_penalty_score')->nullable();
            $table->unsignedSmallInteger('away_penalty_score')->nullable();
            $table->string('referee_name', 120)->nullable();
            $table->text('match_notes')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'stage', 'match_number']);
        });

        // Fase 5.3: Daftar Susunan Pemain / DSP (Match Lineups)
        Schema::create('match_lineups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('tournament_matches')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();
            $table->boolean('is_starting')->default(true);
            $table->unsignedSmallInteger('jersey_number_snapshot')->nullable();
            $table->string('position_snapshot', 20)->nullable();
            $table->boolean('is_captain')->default(false);
            $table->timestamps();

            $table->unique(['match_id', 'player_id']);
        });

        // Fase 5.4: Kejadian Pertandingan — Gol & Kartu Disiplin (Match Events)
        Schema::create('match_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('tournament_matches')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->string('event_type', 40); // goal, penalty_goal, own_goal, yellow_card, second_yellow_card, red_card
            $table->unsignedSmallInteger('minute')->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['match_id', 'event_type']);
        });

        // Fase 6.1: Papan Pengumuman & Undangan Technical Meeting (Event Announcements)
        Schema::create('event_announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->nullable()->constrained('events')->cascadeOnDelete();
            $table->string('title', 180);
            $table->text('content');
            $table->string('meeting_link', 255)->nullable();
            $table->string('attachment_path', 255)->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->boolean('send_email_broadcast')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_announcements');
        Schema::dropIfExists('match_events');
        Schema::dropIfExists('match_lineups');
        Schema::dropIfExists('tournament_matches');
        Schema::dropIfExists('event_group_teams');
        Schema::dropIfExists('event_groups');

        Schema::table('teams', function (Blueprint $table) {
            $cols = array_filter(
                ['home_jersey_color', 'away_jersey_color', 'gk_jersey_color', 'achievement_title'],
                fn ($c) => Schema::hasColumn('teams', $c)
            );
            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};

