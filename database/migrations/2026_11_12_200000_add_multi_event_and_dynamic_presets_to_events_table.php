<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('event_type', 32)->default('tournament')->after('city');
            $table->string('participant_type', 32)->default('team')->after('event_type');
            $table->string('quota_type', 32)->default('fixed')->after('participant_type');
            $table->unsignedInteger('max_participants')->nullable()->after('max_teams');
            $table->unsignedInteger('max_teams')->nullable()->change();
            $table->json('position_quota_caps')->nullable()->after('max_participants');
            $table->json('form_presets')->nullable()->after('position_quota_caps');
            $table->json('custom_fields_schema')->nullable()->after('form_presets');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'event_type',
                'participant_type',
                'quota_type',
                'max_participants',
                'position_quota_caps',
                'form_presets',
                'custom_fields_schema',
            ]);
            $table->unsignedInteger('max_teams')->nullable(false)->change();
        });
    }
};
