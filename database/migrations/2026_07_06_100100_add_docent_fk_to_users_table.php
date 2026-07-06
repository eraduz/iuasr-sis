<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Voegt de foreign key users.docent_id → docenten.id toe nu de tabel
 * docenten bestaat. Bij verwijderen van een docent wordt de koppeling
 * losgelaten (null), niet de gebruiker verwijderd.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('docent_id')->references('id')->on('docenten')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['docent_id']);
        });
    }
};
