<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verlofrecht per medewerker, jaar en verloftype (module HR). Het OPGENOMEN
 * verlof wordt afgeleid uit de goedgekeurde aanvragen; alleen het recht wordt
 * hier opgeslagen (saldo = recht − opgenomen).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verlofsaldi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medewerker_id')->constrained('medewerkers')->cascadeOnDelete();
            $table->unsignedSmallInteger('jaar');
            $table->string('verloftype', 20);
            $table->decimal('recht_uren', 6, 1)->default(0);
            $table->timestamps();

            $table->unique(['medewerker_id', 'jaar', 'verloftype']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verlofsaldi');
    }
};
