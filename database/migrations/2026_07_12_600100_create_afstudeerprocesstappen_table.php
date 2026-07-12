<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De stappen van een afstudeerproces (vijf per proces, één per Afstudeerstap).
 * Elke stap is afvinkbaar met datum en wie hem afvinkte; een optionele opmerking
 * legt bevindingen vast. De verantwoordelijke rol per stap staat vast in de enum.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('afstudeerprocesstappen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('afstudeerproces_id')->constrained('afstudeerprocessen')->cascadeOnDelete();
            $table->string('stap', 30); // Afstudeerstap-waarde
            $table->unsignedTinyInteger('volgorde')->default(0);
            $table->boolean('gereed')->default(false);
            $table->timestamp('gereed_op')->nullable();
            $table->foreignId('gereed_door_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('opmerking')->nullable();
            $table->timestamps();

            $table->unique(['afstudeerproces_id', 'stap']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('afstudeerprocesstappen');
    }
};
