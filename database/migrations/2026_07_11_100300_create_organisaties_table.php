<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Externe organisaties/relaties (stagescholen, schoolbesturen, zorginstellingen,
 * moskeeën, samenwerkingspartners). Het `relatienummer` is een uniek, leesbaar
 * VELD — nooit een koppelsleutel; interne relaties lopen via de surrogaat-id.
 *
 * AVG: dit zijn organisatiegegevens en algemene contactgegevens; er worden hier
 * GEEN leerling-/cliëntgegevens vastgelegd (die blijven bij de organisatie zelf).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organisaties', function (Blueprint $table) {
            $table->id();
            $table->string('relatienummer', 20)->unique();
            $table->string('naam');
            $table->string('kvk_nummer', 20)->nullable();
            $table->string('brin_nummer', 20)->nullable();
            // Organisatietype uit de opzoektabel (optioneel; per opleiding instelbaar).
            $table->foreignId('organisatie_type_id')->nullable()->constrained('organisatie_types')->nullOnDelete();
            $table->string('adres')->nullable();
            $table->string('postcode', 12)->nullable();
            $table->string('plaats')->nullable();
            $table->string('provincie')->nullable();
            $table->string('website')->nullable();
            $table->string('telefoon', 30)->nullable();
            $table->string('email')->nullable();
            $table->boolean('actief')->default(true);
            $table->text('opmerkingen')->nullable();
            $table->timestamps();

            $table->index('naam');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organisaties');
    }
};
