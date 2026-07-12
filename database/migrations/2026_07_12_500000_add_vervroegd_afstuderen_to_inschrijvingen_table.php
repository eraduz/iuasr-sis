<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vervroegd afstuderen. Een student studeert normaal af in het laatste leerjaar,
 * maar kan bij wijze van uitzondering — wegens vrijstellingen en eerder behaalde
 * EC — eerder afstuderen. Dat mag uitsluitend na expliciete toestemming van de
 * EXAMENCOMMISSIE. Deze vlag legt die toestemming per inschrijving vast en geeft
 * de afstudeeractie (Studentenzaken) vrij ook buiten het laatste leerjaar. Wie de
 * vlag zet en wanneer, staat in de audit-log.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inschrijvingen', function (Blueprint $table) {
            $table->boolean('vervroegd_afstuderen')->default(false)->after('afstudeerdatum')
                ->comment('examencommissie geeft vervroegd afstuderen vrij (buiten het laatste leerjaar)');
        });
    }

    public function down(): void
    {
        Schema::table('inschrijvingen', function (Blueprint $table) {
            $table->dropColumn('vervroegd_afstuderen');
        });
    }
};
