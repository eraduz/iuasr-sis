<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vakcode is uniek BINNEN een opleiding, niet daarbuiten.
 *
 * Elf vakken (o.a. B-QR02, B-KL01, B-SG01) worden zowel in de Bachelor
 * Islamitische Theologie als in de Pre-Master GV aangeboden, met dezelfde code.
 * Het zijn aparte onderwijseenheden met een eigen cijferlijst, docent en
 * presentielijst, dus twee rijen met dezelfde code maar een andere opleiding.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vakken', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->unique(['opleiding_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::table('vakken', function (Blueprint $table) {
            $table->dropUnique(['opleiding_id', 'code']);
            $table->unique(['code']);
        });
    }
};
