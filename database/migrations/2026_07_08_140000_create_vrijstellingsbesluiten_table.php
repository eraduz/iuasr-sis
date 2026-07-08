<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vrijstellingsbesluit van de examencommissie, gericht aan Studentenzaken.
 * Workflow: examencommissie legt het besluit vast (open) -> verschijnt op het
 * SZ-dashboard -> Studentenzaken verwerkt het met één klik, waarna de
 * vrijstelling op de vak-toewijzing van de student wordt vastgelegd.
 * Alles blijft binnen het systeem en wordt gelogd (geen e-mail).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vrijstellingsbesluiten', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('studenten')->cascadeOnDelete();
            $table->foreignId('vak_id')->constrained('vakken')->restrictOnDelete();
            $table->string('grondslag', 30)->comment('vooropleiding|evc|eerder_behaald|overig');
            $table->string('besluit', 120)->comment('referentie van het examencommissie-besluit');
            $table->date('besluit_datum');
            $table->text('toelichting')->nullable();
            $table->string('status', 20)->default('open')->comment('open|verwerkt|geannuleerd');
            $table->foreignId('aangemaakt_door_id')->constrained('users');
            $table->foreignId('verwerkt_door_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verwerkt_op')->nullable();
            $table->foreignId('vaktoewijzing_id')->nullable()->constrained('vaktoewijzingen')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vrijstellingsbesluiten');
    }
};
