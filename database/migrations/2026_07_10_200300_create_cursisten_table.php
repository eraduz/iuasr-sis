<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cursisten — de deelnemers aan de cursussen. Een aangepaste, LICHTERE kopie van
 * de studenten-structuur: cursussen vallen buiten Studentenzaken en kennen geen
 * BSN/DUO-regime. Iemand die zowel student als cursist is, staat in beide tabellen.
 *
 * Het cursistnummer is een uniek, leesbaar VELD — nooit een koppelsleutel; de
 * interne identiteit blijft de surrogaatsleutel (id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cursisten', function (Blueprint $table) {
            $table->id();
            $table->string('cursistnummer')->unique();
            $table->string('aanhef')->nullable();
            $table->string('voornaam');
            $table->string('tussenvoegsel')->nullable();
            $table->string('achternaam');
            $table->date('geboortedatum')->nullable();
            $table->string('geslacht')->nullable();
            $table->string('adres')->nullable();
            $table->string('postcode')->nullable();
            $table->string('woonplaats')->nullable();
            $table->string('telefoon')->nullable();
            $table->string('email')->nullable();
            $table->string('status', 20)->default('actief')->comment('actief | inactief');
            $table->text('opmerkingen')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cursisten');
    }
};
