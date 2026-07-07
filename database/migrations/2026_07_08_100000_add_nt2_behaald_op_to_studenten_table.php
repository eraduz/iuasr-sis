<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NT2-examen: datum waarop de student het examen succesvol heeft afgerond.
 * De deadline (1 jaar vanaf inschrijfdatum) wordt afgeleid, niet opgeslagen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('studenten', function (Blueprint $table) {
            $table->date('nt2_behaald_op')->nullable()->after('nt2_examen_vereist');
        });
    }

    public function down(): void
    {
        Schema::table('studenten', function (Blueprint $table) {
            $table->dropColumn('nt2_behaald_op');
        });
    }
};
