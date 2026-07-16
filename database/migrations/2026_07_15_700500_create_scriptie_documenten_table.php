<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documenten binnen een scriptietraject (plan van aanpak, eindversie,
 * plagiaatrapport, presentatie, overig). Op de PRIVATE schijf (buiten de webroot),
 * met versiebeheer via `vorige_versie_id` (een nieuwe versie verwijst naar de
 * vorige; het oude bestand blijft bewaard). Inzage/upload/verwijdering wordt gelogd.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scriptie_documenten', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scriptie_id')->constrained('scripties')->cascadeOnDelete();
            $table->string('categorie', 40);   // ScriptieDocument::CATEGORIEEN
            $table->string('titel')->nullable();
            $table->string('bestandsnaam');
            $table->string('pad');             // pad op de private schijf
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('grootte')->nullable();
            $table->unsignedSmallInteger('versie')->default(1);
            $table->foreignId('vorige_versie_id')->nullable()
                ->constrained('scriptie_documenten')->nullOnDelete();
            $table->foreignId('geupload_door_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['scriptie_id', 'categorie']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scriptie_documenten');
    }
};
