<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cursussen (module Cursussen Administratie). Elke cursus heeft een cursusgeld
 * en (later) een verantwoordelijke directeur. Nieuwe cursussen en tarieven zijn
 * simpelweg extra rijen — het systeem is daar niet op vastgezet.
 *
 * De drie huidige cursussen worden meteen ingevoegd (opdrachtgever): Arabische
 * Taal € 265, Hifz Programma € 330, Certificaatprogramma / Ijaaza € 430.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cursussen', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('naam');
            $table->text('omschrijving')->nullable();
            $table->decimal('cursusgeld', 10, 2)->default(0);
            $table->date('startdatum')->nullable();
            $table->date('einddatum')->nullable();
            // Verantwoordelijke directeur (een gebruiker). De directeurmodule met
            // toegangsbeperking volgt in een latere fase; nu nog niet gekoppeld.
            $table->foreignId('directeur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('actief')->default(true);
            $table->timestamps();
        });

        $nu = now();
        DB::table('cursussen')->insert([
            ['code' => 'ARAB-TAAL', 'naam' => 'Arabische Taal', 'cursusgeld' => 265.00, 'actief' => true, 'created_at' => $nu, 'updated_at' => $nu],
            ['code' => 'HIFZ', 'naam' => 'Hifz Programma', 'cursusgeld' => 330.00, 'actief' => true, 'created_at' => $nu, 'updated_at' => $nu],
            ['code' => 'IJAZA', 'naam' => 'Certificaatprogramma / Ijaaza', 'cursusgeld' => 430.00, 'actief' => true, 'created_at' => $nu, 'updated_at' => $nu],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('cursussen');
    }
};
