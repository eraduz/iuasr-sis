<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stageperioden: de per opleiding vastgelegde stages in het curriculum, elk met
 * een urennorm. Bijvoorbeeld voor de Bachelor Islamitische Theologie de
 * Verkennende stage (jaar 2, 140 u), Stage 1 (jaar 3, 280 u) en Grote Stage 2
 * (jaar 4, 560 u); voor de Master IGV een Snuffelstage (40 u) en een Grote stage
 * (480 u). Datagedreven zodat andere opleidingen (PABO e.a.) hun eigen perioden
 * kunnen krijgen zonder codewijziging.
 *
 * Een concrete plaatsing (`stages`) verwijst naar de gekozen stageperiode en legt
 * de daadwerkelijk gemaakte uren vast.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stageperioden', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opleiding_id')->constrained('opleidingen')->cascadeOnDelete();
            $table->string('naam');
            $table->string('code', 30)->nullable();
            // Leerjaar waarin de stage valt; leeg voor niet-jaargebonden stages (master).
            $table->unsignedTinyInteger('leerjaar')->nullable();
            $table->unsignedSmallInteger('verplichte_uren');
            $table->unsignedTinyInteger('volgorde')->default(0);
            $table->boolean('actief')->default(true);
            $table->timestamps();

            $table->unique(['opleiding_id', 'naam']);
            $table->index(['opleiding_id', 'actief']);
        });

        Schema::table('stages', function (Blueprint $table) {
            $table->foreignId('stageperiode_id')->nullable()->after('opleiding_id')
                ->constrained('stageperioden')->nullOnDelete();
            // Daadwerkelijk gemaakte/afgesproken uren van deze plaatsing (norm staat
            // op de stageperiode; hier de concrete uren, standaard voorgevuld).
            $table->unsignedSmallInteger('uren')->nullable()->after('einddatum');
        });
    }

    public function down(): void
    {
        Schema::table('stages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stageperiode_id');
            $table->dropColumn('uren');
        });

        Schema::dropIfExists('stageperioden');
    }
};
