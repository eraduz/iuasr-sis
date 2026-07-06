<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Student — masterrecord. `id` is de betekenisloze surrogaatsleutel;
 * `studentnummer` is een uniek, leesbaar VELD (nooit een koppelsleutel).
 *
 * Gevoelige velden (bsn, rekeningnummer) worden VERSLEUTELD opgeslagen via een
 * cast; hier zijn het daarom text-kolommen. `bsn_hash` is een niet-omkeerbare
 * hash uitsluitend voor duplicaatdetectie (versleutelde waarden zijn niet
 * doorzoekbaar). BSN wordt pas gevuld na akkoord van de FG.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studenten', function (Blueprint $table) {
            $table->id(); // surrogaatsleutel
            $table->string('studentnummer')->unique()->comment('uniek leesbaar veld, geen koppelsleutel');

            $table->string('aanhef')->nullable();
            $table->string('voornaam');
            $table->string('roepnaam')->nullable();
            $table->string('tussenvoegsel')->nullable();
            $table->string('achternaam');
            $table->date('geboortedatum')->nullable();
            $table->string('geboorteplaats')->nullable();
            $table->string('geslacht')->nullable();

            $table->foreignId('nationaliteit_id')->nullable()->constrained('nationaliteiten')->nullOnDelete();
            $table->foreignId('land_id')->nullable()->constrained('landen')->nullOnDelete();

            $table->string('adres')->nullable();
            $table->string('postcode')->nullable();
            $table->string('woonplaats')->nullable();
            $table->string('telefoon')->nullable();
            $table->string('email')->nullable();
            $table->string('email_prive')->nullable();

            $table->string('vooropleiding')->nullable();
            $table->string('diploma')->nullable();

            // Gevoelige, versleutelde velden (AVG). Text i.v.m. ciphertext-lengte.
            $table->text('bsn')->nullable()->comment('versleuteld; inzage gelogd; alleen bevoegde rollen');
            $table->string('bsn_hash', 64)->nullable()->index()->comment('HMAC-hash, uitsluitend duplicaatdetectie');
            $table->text('rekeningnummer')->nullable()->comment('versleuteld');

            $table->text('opmerkingen')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studenten');
    }
};
