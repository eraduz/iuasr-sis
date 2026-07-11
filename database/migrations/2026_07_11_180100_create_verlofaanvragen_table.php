<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verlofaanvragen (module HR). Workflow: aanvraag (self-service) → goedkeuring
 * door de leidinggevende (HR als terugval) → registratie. Alleen goedgekeurde
 * aanvragen tellen mee voor het opgenomen verlof.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verlofaanvragen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medewerker_id')->constrained('medewerkers')->cascadeOnDelete();
            $table->string('verloftype', 20);
            $table->date('van');
            $table->date('tot');
            $table->decimal('uren', 6, 1)->default(0);
            $table->string('status', 20)->default('aangevraagd');
            $table->text('reden')->nullable();
            $table->foreignId('aangevraagd_door_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('beoordelaar_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('beoordeeld_op')->nullable();
            $table->text('opmerking_beoordelaar')->nullable();
            $table->timestamps();

            $table->index(['medewerker_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verlofaanvragen');
    }
};
