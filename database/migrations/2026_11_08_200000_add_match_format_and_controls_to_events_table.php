<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('match_format', 32)->default('11_vs_11')->after('format');
            $table->unsignedTinyInteger('starting_players_count')->default(11)->after('match_format');
            $table->unsignedSmallInteger('dsp_cutoff_minutes')->default(30)->after('starting_players_count');
            $table->boolean('is_group_locked')->default(false)->after('status');
            $table->boolean('certificates_released')->default(false)->after('is_group_locked');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'match_format',
                'starting_players_count',
                'dsp_cutoff_minutes',
                'is_group_locked',
                'certificates_released',
            ]);
        });
    }
};

