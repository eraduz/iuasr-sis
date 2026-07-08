<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Archief + logregistratie van digitaal ondertekende PDF-documenten. Elk
 * document krijgt een unieke verificatiecode en een SHA-256 (echtheidskenmerk):
 * wie het heeft ondertekend, wanneer en aan wie het is verstrekt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ondertekende_documenten', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();          // publieke verificatiecode
            $table->string('type');                    // bv. verklaring:studentbewijs, upload
            $table->string('titel');
            $table->foreignId('student_id')->nullable()->constrained('studenten')->nullOnDelete();
            $table->string('ontvanger')->nullable();   // aan wie/welke organisatie verstrekt
            $table->foreignId('uitgegeven_door_id')->nullable()->constrained('users')->nullOnDelete();
            $table->char('sha256', 64);                // echtheidskenmerk van de PDF-bytes
            $table->string('bestandsnaam');
            $table->string('pad');                     // opslag op de private schijf
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ondertekende_documenten');
    }
};
