<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Collegegeldtarieven per studiejaar (periode). Toekomstbestendig: een
 * standaardtarief per jaar (opleiding_id null) én de mogelijkheid om per
 * opleiding een afwijkend tarief vast te leggen. Wordt jaarlijks door de
 * Studentenadministratie bijgewerkt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collegegeld_tarieven', function (Blueprint $table) {
            $table->id();
            $table->foreignId('periode_id')->constrained('perioden')->cascadeOnDelete();
            // null = standaardtarief voor alle opleidingen in dit studiejaar.
            $table->foreignId('opleiding_id')->nullable()->constrained('opleidingen')->cascadeOnDelete();
            $table->decimal('bedrag', 10, 2);
            $table->unsignedTinyInteger('aantal_termijnen')->default(5);
            $table->foreignId('ingesteld_door_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['periode_id', 'opleiding_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collegegeld_tarieven');
    }
};
