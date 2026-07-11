<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opzoektabel met de soorten organisatie (basisschool, schoolbestuur,
 * zorginstelling, moskee, samenwerkingspartner, ...). Configureerbaar PER
 * OPLEIDING: `opleiding_id` = null betekent 'voor alle opleidingen', een
 * gevulde waarde beperkt het type tot die opleiding. Zo kan de PABO andere
 * organisatietypes voeren dan de Master IGV of de Bachelor Theologie.
 *
 * Beheerd via Opzoektabellen (generieke referentie-CRUD).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organisatie_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('naam');
            // null = geldt voor alle opleidingen; anders opleidingspecifiek.
            $table->foreignId('opleiding_id')->nullable()->constrained('opleidingen')->nullOnDelete();
            $table->boolean('actief')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organisatie_types');
    }
};
