<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cursusgeldbetalingen. Een cursusgeld wordt doorgaans in één keer voldaan bij
 * de inschrijving (geen termijnen zoals het collegegeld). Per betaling: de
 * methode, het bedrag, de datum, de status en een referentienummer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cursusbetalingen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cursusinschrijving_id')->constrained('cursusinschrijvingen')->cascadeOnDelete();
            $table->string('betaalmethode', 20)->comment('ideal | overboeking | contant');
            $table->decimal('bedrag', 10, 2);
            $table->date('betaaldatum');
            $table->string('betalingsstatus', 20)->default('betaald')->comment('in_afwachting | betaald | mislukt | terugbetaald');
            $table->string('referentienummer', 100)->nullable();
            $table->text('opmerking')->nullable();
            $table->foreignId('geregistreerd_door_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['cursusinschrijving_id', 'betalingsstatus']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cursusbetalingen');
    }
};
