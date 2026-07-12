<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Offboarding — datum en reden van uitdiensttreding. De status "uit_dienst"
 * bestond al, maar er was geen plek om de daadwerkelijke uit-dienstdatum (laatste
 * werkdag) en de reden vast te leggen. Nodig voor een sluitend personeelsdossier
 * en voor het correct afsluiten van het lopende dienstverband.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medewerkers', function (Blueprint $table) {
            $table->date('uit_dienst_datum')->nullable()->after('status')
                ->comment('laatste dienstdag; verplicht bij status uit_dienst');
            $table->string('uit_dienst_reden')->nullable()->after('uit_dienst_datum')
                ->comment('reden van uitdiensttreding (bv. eigen verzoek, einde contract, pensioen)');
        });
    }

    public function down(): void
    {
        Schema::table('medewerkers', function (Blueprint $table) {
            $table->dropColumn(['uit_dienst_datum', 'uit_dienst_reden']);
        });
    }
};
