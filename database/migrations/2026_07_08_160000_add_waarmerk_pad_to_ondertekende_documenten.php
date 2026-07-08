<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bij een geüploade PDF bewaren we naast het originele bestand (pad) ook het
 * gegenereerde digitale waarmerk-certificaat (waarmerk_pad).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ondertekende_documenten', function (Blueprint $table) {
            $table->string('waarmerk_pad')->nullable()->after('pad');
        });
    }

    public function down(): void
    {
        Schema::table('ondertekende_documenten', function (Blueprint $table) {
            $table->dropColumn('waarmerk_pad');
        });
    }
};
