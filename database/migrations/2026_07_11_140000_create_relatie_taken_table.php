<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Taken bij een organisatie (module Relatiebeheer & Stagebeheer), gemodelleerd
 * naar Microsoft Graph `todoTask` — net als de takenlijst van Studentenzaken,
 * maar met een eigen tabel zodat de administraties gescheiden blijven. Optioneel
 * gekoppeld aan een stage. 'Te laat' is afgeleid (vervaldatum + status), geen kolom.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relatie_taken', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisatie_id')->constrained('organisaties')->cascadeOnDelete();
            $table->foreignId('stage_id')->nullable()->constrained('stages')->nullOnDelete();
            $table->string('titel');
            $table->text('omschrijving')->nullable();
            $table->foreignId('toegewezen_aan_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('aangemaakt_door_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('startdatum')->nullable();
            $table->date('vervaldatum')->nullable();
            $table->string('prioriteit', 20)->default('normaal');
            $table->string('status', 20)->default('open');
            $table->timestamp('afgerond_op')->nullable();
            $table->foreignId('afgerond_door_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organisatie_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relatie_taken');
    }
};
