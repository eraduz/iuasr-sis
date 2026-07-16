<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vergaderingen van het Stichtingsbestuur of de Raad van Toezicht: datum, orgaan
 * (soort vergadering), besproken onderwerpen en genomen besluiten. De aanwezigheid
 * per lid staat in `bestuursvergadering_aanwezigheden`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bestuursvergaderingen', function (Blueprint $table) {
            $table->id();
            $table->date('datum');
            $table->string('orgaan', 30);   // Bestuursorgaan (soort van vergadering)
            $table->string('locatie')->nullable();
            $table->text('onderwerpen')->nullable();
            $table->text('besluiten')->nullable();
            $table->text('opmerking')->nullable();
            $table->foreignId('genotuleerd_door_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['orgaan', 'datum']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bestuursvergaderingen');
    }
};
