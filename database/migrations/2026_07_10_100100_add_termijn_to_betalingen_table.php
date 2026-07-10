<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Termijnnummer op een betaling: aan welke factuur (1 t/m 5) de boekhouding de
 * betaling toerekent. Samen met `inschrijving_id` identificeert dit de termijn;
 * er is bewust GEEN aparte facturentabel, omdat het termijnschema volledig uit
 * het jaartarief, de betaalregeling en de inschrijvingsduur is af te leiden.
 * Zo kan een schema nooit verouderd raken t.o.v. de inschrijving.
 *
 * Blijft de termijn leeg (bijvoorbeeld bij een bulk-import), dan wordt de
 * betaling automatisch toegerekend aan de oudste nog openstaande termijn.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('betalingen', function (Blueprint $table) {
            $table->unsignedTinyInteger('termijn')->nullable()->after('bedrag')
                ->comment('1..5; leeg = automatisch toerekenen aan oudste openstaande termijn');
        });
    }

    public function down(): void
    {
        Schema::table('betalingen', function (Blueprint $table) {
            $table->dropColumn('termijn');
        });
    }
};
