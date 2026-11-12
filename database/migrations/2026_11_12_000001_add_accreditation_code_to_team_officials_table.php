<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_officials', function (Blueprint $table) {
            $table->string('accreditation_code', 64)->nullable()->unique()->after('screening_status');
        });

        DB::table('team_officials')
            ->whereNull('accreditation_code')
            ->orderBy('id')
            ->eachById(function (object $official): void {
                DB::table('team_officials')
                    ->where('id', $official->id)
                    ->update(['accreditation_code' => 'OFF-'.Str::upper(Str::random(24))]);
            });
    }

    public function down(): void
    {
        Schema::table('team_officials', function (Blueprint $table) {
            $table->dropUnique(['accreditation_code']);
            $table->dropColumn('accreditation_code');
        });
    }
};
