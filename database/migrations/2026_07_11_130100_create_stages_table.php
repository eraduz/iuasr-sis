<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stages: de plaatsing van een student op een organisatie/stageplaats, met de
 * begeleiders vanuit de opleiding (stagebegeleider = een gebruiker) en op de
 * locatie (werkplekbegeleider = een contactpersoon). Statusverloop en een
 * (gevoelige, rolgescheiden) beoordeling voldoende/onvoldoende.
 *
 * Het `stagenummer` is een uniek, leesbaar VELD — nooit een koppelsleutel.
 * AVG: de beoordeling gaat over de student; inzage/mutatie wordt gelogd.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stages', function (Blueprint $table) {
            $table->id();
            $table->string('stagenummer', 20)->unique();
            $table->foreignId('student_id')->constrained('studenten')->cascadeOnDelete();
            $table->foreignId('organisatie_id')->constrained('organisaties')->cascadeOnDelete();
            $table->foreignId('stageplaats_id')->nullable()->constrained('stageplaatsen')->nullOnDelete();
            $table->foreignId('opleiding_id')->constrained('opleidingen')->cascadeOnDelete();
            // Begeleider vanuit de opleiding (instituutsopleider/stagebegeleider).
            $table->foreignId('stagebegeleider_id')->nullable()->constrained('users')->nullOnDelete();
            // Begeleider op de locatie (werkplekbegeleider) = een contactpersoon.
            $table->foreignId('werkplekbegeleider_id')->nullable()->constrained('contactpersonen')->nullOnDelete();
            $table->date('startdatum')->nullable();
            $table->date('einddatum')->nullable();
            $table->string('status', 20)->default('aangevraagd');
            $table->string('beoordeling', 20)->nullable(); // voldoende | onvoldoende
            $table->text('beoordeling_toelichting')->nullable();
            $table->timestamps();

            $table->index(['organisatie_id', 'status']);
            $table->index('opleiding_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stages');
    }
};
