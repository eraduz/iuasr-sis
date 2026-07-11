<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dienstverbanden (contracthistorie) per medewerker — module HR. Meerdere per
 * medewerker (verlenging/historie). De FTE wordt AFGELEID uit `uren_per_week` ÷
 * de voltijdsnorm (config `sis.hr.voltijd_uren`), niet dubbel opgeslagen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dienstverbanden', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medewerker_id')->constrained('medewerkers')->cascadeOnDelete();
            $table->string('contracttype', 20)->default('tijdelijk'); // vast | tijdelijk
            $table->date('startdatum');
            $table->date('einddatum')->nullable();
            $table->decimal('uren_per_week', 4, 1)->default(0);
            $table->foreignId('functie_id')->nullable()->constrained('functies')->nullOnDelete();
            $table->foreignId('afdeling_id')->nullable()->constrained('afdelingen')->nullOnDelete();
            $table->text('opmerking')->nullable();
            $table->timestamps();

            $table->index(['medewerker_id', 'startdatum']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dienstverbanden');
    }
};
