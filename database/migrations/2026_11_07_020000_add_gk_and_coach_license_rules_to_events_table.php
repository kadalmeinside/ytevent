<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->unsignedTinyInteger('min_gk_per_team')->default(2)->after('max_players_per_team');
            $table->boolean('show_coach_license')->default(true)->after('max_officials_per_team');
            $table->boolean('require_coach_license')->default(false)->after('show_coach_license');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'min_gk_per_team',
                'show_coach_license',
                'require_coach_license',
            ]);
        });
    }
};
