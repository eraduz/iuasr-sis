<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De stand per stap van een scriptietraject: één rij per stap (elf per traject).
 * Stuurt de tabbladen en de sequentiële afvinklogica. `status` is een sleutel uit
 * Scriptiestap::statussen(); `gereed` markeert de stap als afgerond (met wie/wanneer).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scriptie_stapstanden', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scriptie_id')->constrained('scripties')->cascadeOnDelete();
            $table->string('stap', 40);                       // Scriptiestap-waarde
            $table->unsignedTinyInteger('volgorde')->default(0);
            $table->string('status', 40)->nullable();         // sleutel uit Scriptiestap::statussen()
            $table->boolean('gereed')->default(false);
            $table->timestamp('gereed_op')->nullable();
            $table->foreignId('gereed_door_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('opmerking')->nullable();
            $table->timestamps();

            $table->unique(['scriptie_id', 'stap']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scriptie_stapstanden');
    }
};
