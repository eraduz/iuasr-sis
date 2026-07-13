<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module Balie/Receptie — één chronologisch logboek voor alle stromen aan de
 * ingang: inkomende en uitgaande telefoongesprekken, bezoekers, en inkomende en
 * uitgaande post.
 *
 * Waarom één tabel en niet vijf: de vijf stromen delen vrijwel dezelfde velden
 * (moment, tegenpartij, voor wie, toelichting). Met `soort` + `richting` als
 * discriminator blijft het één dagboek dat in één keer doorzoekbaar, filterbaar
 * en exporteerbaar is — precies wat de opdracht vraagt ("alle registraties
 * doorzoekbaar", "chronologisch weergegeven"). De velden die niet voor elk soort
 * gelden (onderwerp bij post, vertrek bij bezoek) zijn nullable; de
 * invoerformulieren en de modelvalidatie tonen per soort alleen wat van
 * toepassing is.
 *
 * Koppelingen zijn echte foreign keys op surrogaatsleutels:
 *   - `medewerker_id` — voor wie het bestemd is / met wie de afspraak is.
 *   - `geregistreerd_door_user_id` — welke baliemedewerker het vastlegde.
 * `afdeling` is de terugval wanneer iets niet voor één persoon maar voor een
 * afdeling bestemd is (bijv. "Studentenzaken").
 *
 * AVG: bezoekers- en belgegevens zijn persoonsgegevens. In ontwikkeling en test
 * staat hier uitsluitend synthetische data (zie BalieSeeder).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('balie_registraties', function (Blueprint $table) {
            $table->id();

            // Wat voor registratie is dit, en welke kant op?
            $table->string('soort', 20);                      // BalieSoort: telefoon | bezoek | post
            $table->string('richting', 20);                   // BalieRichting: inkomend | uitgaand

            // Wanneer. Voor telefoon en post is dit het moment van het gesprek of
            // de ontvangst/verzending; voor een bezoek de aankomst.
            $table->dateTime('datum_tijd');

            // Alleen bij een bezoek: wanneer de bezoeker het pand weer verliet.
            // Leeg = de bezoeker is (volgens de registratie) nog binnen.
            $table->dateTime('vertrokken_op')->nullable();

            // Waar ging het over. Bij post niet van toepassing (blijft leeg).
            $table->string('onderwerp')->nullable();

            // De tegenpartij: de beller, de bezoeker, de afzender of de geadresseerde.
            $table->string('contact_naam');
            $table->string('contact_organisatie')->nullable();
            $table->string('contact_telefoon', 30)->nullable();

            // Voor wie is het bestemd / met wie is de afspraak. Bij voorkeur een
            // echte medewerker; anders een afdeling.
            $table->foreignId('medewerker_id')->nullable()->constrained('medewerkers')->nullOnDelete();
            $table->string('afdeling')->nullable();

            $table->text('toelichting')->nullable();

            // Wie legde de registratie vast (verantwoording, niet te wissen).
            $table->foreignId('geregistreerd_door_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Het logboek wordt vrijwel altijd chronologisch en per soort gelezen.
            $table->index(['datum_tijd']);
            $table->index(['soort', 'richting']);
            $table->index(['medewerker_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('balie_registraties');
    }
};
