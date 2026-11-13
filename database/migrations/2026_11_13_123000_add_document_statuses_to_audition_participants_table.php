<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audition_participants', function (Blueprint $table) {
            $table->json('document_statuses')->nullable()->after('additional_doc_path');
        });
    }

    public function down(): void
    {
        Schema::table('audition_participants', function (Blueprint $table) {
            $table->dropColumn('document_statuses');
        });
    }
};

