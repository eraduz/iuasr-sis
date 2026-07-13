<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verrijking van de catalogus met een externe bibliografische bron (Open Library):
 * ISBN, uitgavejaar en een gecorrigeerde schrijfwijze van titel en auteur.
 *
 * Waarom een aparte tabel en niet stilletjes de publicatie overschrijven:
 *
 *  1. VERANTWOORDING. Per titel is naspeurbaar wat de externe bron zei, hoe zeker
 *     de match was, en of het is toegepast. Zonder dat is een automatische
 *     correctie niet te controleren en niet terug te draaien.
 *  2. ZEKERHEID BOVEN VOLLEDIGHEID (keuze opdrachtgever 2026-07-13): alleen bij
 *     een ZEKERE match wordt de publicatie gewijzigd. Twijfelgevallen worden
 *     overgeslagen en als 'onzeker' vastgelegd — nooit toegepast.
 *  3. IDEMPOTENT. Een titel die al is bevraagd wordt niet opnieuw bevraagd; zo
 *     kan het commando in porties draaien zonder de externe bron te belasten.
 *
 * De oude waarde wordt bewaard (`oude_titel`, `oude_auteur`), zodat een correctie
 * altijd terug te draaien is.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bibliotheek_verrijkingen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publicatie_id')->constrained('bibliotheek_publicaties')->cascadeOnDelete();

            $table->string('bron', 40)->default('openlibrary');
            // toegepast | onzeker | geen_treffer | fout
            $table->string('status', 20);

            // Wat de externe bron teruggaf.
            $table->string('gevonden_titel')->nullable();
            $table->string('gevonden_auteur')->nullable();
            $table->string('isbn', 20)->nullable();
            $table->unsignedSmallInteger('jaar')->nullable();

            // Hoe zeker was de match (0.00 - 1.00)? Bepaalt of er iets is toegepast.
            $table->decimal('score', 4, 3)->nullable();

            // De oude waarden, zodat een correctie terug te draaien is.
            $table->string('oude_titel')->nullable();
            $table->string('oude_auteur')->nullable();

            $table->string('toelichting')->nullable();
            $table->dateTime('opgehaald_op');

            // Eén poging per publicatie per bron: zo blijft het herhaalbaar.
            $table->unique(['publicatie_id', 'bron']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bibliotheek_verrijkingen');
    }
};
