<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 50%-aanwezigheidsregeling. Toegekend per INSCHRIJVING (student × opleiding ×
 * studiejaar), zodat een dubbel ingeschreven student de regeling in de ene
 * opleiding wel en in de andere niet kan hebben, en zij bij herinschrijving
 * bewust opnieuw wordt toegekend. Studentenzaken zet het vinkje (met
 * toestemming van de directie); de mutatie wordt gelogd.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inschrijvingen', function (Blueprint $table) {
            $table->boolean('aanwezigheidsregeling_50')->default(false)->after('betaalwijze');
        });
    }

    public function down(): void
    {
        Schema::table('inschrijvingen', function (Blueprint $table) {
            $table->dropColumn('aanwezigheidsregeling_50');
        });
    }
};
