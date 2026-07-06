<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Genormaliseerde toetsstructuur: per vak één of meer onderdelen met weging.
 * Vervangt de vaste blok-kolommen (BL1–BL4) van het oude systeem.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('toetsonderdelen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vak_id')->constrained('vakken')->cascadeOnDelete();
            $table->string('code')->nullable();
            $table->string('naam');
            $table->string('type')->comment('werkstuk | tentamen | mondeling | presentatie | portfolio | ...');
            $table->decimal('weging', 5, 2)->default(1)->comment('aandeel in eindcijfer');
            $table->boolean('telt_mee')->default(true)->comment('telt mee voor EC-toekenning');
            $table->unsignedTinyInteger('volgorde')->default(1);
            $table->timestamps();

            $table->unique(['vak_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('toetsonderdelen');
    }
};
