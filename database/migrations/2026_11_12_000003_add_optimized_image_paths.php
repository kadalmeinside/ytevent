<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table): void {
            $table->string('logo_optimized_path', 255)->nullable()->after('logo_path');
        });

        Schema::table('events', function (Blueprint $table): void {
            $table->string('banner_optimized_path', 255)->nullable()->after('banner_path');
        });

        Schema::table('players', function (Blueprint $table): void {
            $table->string('photo_optimized_path', 255)->nullable()->after('photo_path');
        });

        Schema::table('team_officials', function (Blueprint $table): void {
            $table->string('photo_optimized_path', 255)->nullable()->after('photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('team_officials', fn (Blueprint $table) => $table->dropColumn('photo_optimized_path'));
        Schema::table('players', fn (Blueprint $table) => $table->dropColumn('photo_optimized_path'));
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn('banner_optimized_path'));
        Schema::table('teams', fn (Blueprint $table) => $table->dropColumn('logo_optimized_path'));
    }
};
