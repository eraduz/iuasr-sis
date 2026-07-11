<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agenda-afspraken bij een organisatie (module Relatiebeheer & Stagebeheer):
 * schoolbezoeken, stagebezoeken, evaluaties, overleggen en open dagen. Model naar
 * Microsoft Graph `event` (met het oog op latere Outlook-synchronisatie).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agenda_afspraken', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisatie_id')->constrained('organisaties')->cascadeOnDelete();
            $table->foreignId('stage_id')->nullable()->constrained('stages')->nullOnDelete();
            $table->foreignId('medewerker_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 30);
            $table->date('datum');
            $table->time('tijd_van')->nullable();
            $table->time('tijd_tot')->nullable();
            $table->string('locatie')->nullable();
            $table->string('status', 20)->default('gepland'); // gepland | afgerond | geannuleerd
            $table->text('omschrijving')->nullable();
            $table->timestamps();

            $table->index(['organisatie_id', 'datum']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda_afspraken');
    }
};
