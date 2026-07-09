<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Koppeltabel Directie <-> Opleiding.
 *
 * Een directielid ziet uitsluitend studenten (en cijfers/rapporten) van de
 * opleiding(en) waaraan het is toegewezen. Zonder toewijzing ziet een
 * directielid niets — bewust restrictief (need-to-know, AVG). Echte foreign
 * keys met cascade delete, geen tekstuele koppelsleutels.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('directie_opleidingen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('opleiding_id')->constrained('opleidingen')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'opleiding_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('directie_opleidingen');
    }
};
