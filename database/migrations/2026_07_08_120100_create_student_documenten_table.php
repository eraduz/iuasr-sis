<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documenten die van de student worden ontvangen (identiteitsbewijs, diploma,
 * cijferlijst, pasfoto, ...). De bestanden zelf staan op de private schijf,
 * buiten de webroot; hier alleen de metadata. Inzage/afgifte wordt gelogd.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_documenten', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('studenten')->cascadeOnDelete();
            $table->string('soort');           // id_voor, id_achter, diploma, cijferlijst, pasfoto, overig
            $table->string('bestandsnaam');    // oorspronkelijke naam
            $table->string('pad');             // opslagpad op de private schijf
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('grootte')->default(0);
            $table->foreignId('geupload_door_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['student_id', 'soort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_documenten');
    }
};
