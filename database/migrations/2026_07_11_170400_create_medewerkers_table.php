<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Medewerkers — personeelsmaster (module HR / Personeelszaken).
 *
 * Surrogaatsleutel; het leesbare `personeelsnummer` is een uniek VELD, nooit een
 * koppelsleutel. `user_id` koppelt (optioneel) aan een login voor self-service.
 * BSN is een apart beschermd, versleuteld en toegangsgelogd veld — standaard uit
 * (config `sis.hr.bsn_ingeschakeld`) tot akkoord van de FG.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medewerkers', function (Blueprint $table) {
            $table->id();
            $table->string('personeelsnummer', 20)->unique();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->foreignId('docent_id')->nullable()->constrained('docenten')->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('medewerkers')->nullOnDelete();
            $table->foreignId('afdeling_id')->nullable()->constrained('afdelingen')->nullOnDelete();
            $table->foreignId('functie_id')->nullable()->constrained('functies')->nullOnDelete();
            $table->string('aanhef', 20)->nullable();
            $table->string('voornaam');
            $table->string('tussenvoegsel')->nullable();
            $table->string('achternaam');
            $table->date('geboortedatum')->nullable();
            // Gevoelig (AVG): versleuteld opgeslagen, inzage gelogd. Standaard uit.
            $table->text('bsn')->nullable();
            $table->string('bsn_hash')->nullable();
            $table->string('adres')->nullable();
            $table->string('postcode', 12)->nullable();
            $table->string('woonplaats')->nullable();
            $table->string('telefoon', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('email_prive')->nullable();
            $table->string('status', 20)->default('actief');
            $table->boolean('actief')->default(true);
            $table->text('opmerkingen')->nullable();
            $table->timestamps();

            $table->index(['achternaam', 'voornaam']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medewerkers');
    }
};
