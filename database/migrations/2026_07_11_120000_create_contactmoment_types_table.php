<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opzoektabel met de soorten contactmoment (telefoon, e-mail, Teams, bezoek,
 * stagebezoek, overleg, klacht, evaluatie, ...). Beheerd via Opzoektabellen,
 * zodat de school er zelf types aan kan toevoegen. Niet opleidingspecifiek.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contactmoment_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('naam');
            $table->unsignedTinyInteger('volgorde')->default(0);
            $table->boolean('actief')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contactmoment_types');
    }
};
