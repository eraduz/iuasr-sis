<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Betaalregeling per inschrijving: vijf termijnen (september, november,
 * januari, maart, mei) of één factuur voor het volledige jaarbedrag.
 * Studentenzaken legt dit vast; de Financiële Administratie boekt per termijn.
 *
 * De bestaande kolom `betaalwijze` op inschrijvingen blijft staan voor de
 * historie, maar wordt niet meer gebruikt: zij mengde regeling (termijnen) en
 * betaalwijze (contant). De betaalwijze hoort bij een betaling, niet bij de
 * inschrijving.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inschrijvingen', function (Blueprint $table) {
            $table->string('betaalregeling', 20)->default('termijnen')->after('betaalwijze')
                ->comment('termijnen | volledig');
        });

        // Bestaande inschrijvingen die 'contant' als betaalwijze hadden, betaalden
        // het jaarbedrag in één keer: die krijgen de regeling 'volledig'.
        \Illuminate\Support\Facades\DB::table('inschrijvingen')
            ->where('betaalwijze', 'contant')->update(['betaalregeling' => 'volledig']);
    }

    public function down(): void
    {
        Schema::table('inschrijvingen', function (Blueprint $table) {
            $table->dropColumn('betaalregeling');
        });
    }
};
