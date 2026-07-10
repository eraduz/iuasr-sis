<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Betalingsafspraak: de student heeft nog niet betaald, maar heeft met de
 * Financiële Administratie afgesproken dat vóór een bepaalde datum te doen.
 * Zolang die afspraak loopt vervallen de blokkades op verklaringen en
 * herinschrijven — de SCHULD blijft bestaan en blijft zichtbaar.
 *
 * `geldig_tot` is verplicht: na die datum keert de blokkade automatisch terug,
 * tenzij de schuld inmiddels is voldaan. Zo blijft een vergeten afspraak niet
 * eeuwig openstaan.
 *
 * Vastleggen en intrekken doet uitsluitend de Financiële Administratie (of
 * Beheer); Studentenzaken kan haar eigen blokkade dus niet opheffen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('betalingsafspraken', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('studenten')->cascadeOnDelete();
            $table->date('geldig_tot');
            $table->string('reden', 200);

            $table->foreignId('vastgelegd_door_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ingetrokken_op')->nullable();
            $table->foreignId('ingetrokken_door_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['student_id', 'ingetrokken_op', 'geldig_tot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('betalingsafspraken');
    }
};
