<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Onboarding-/offboarding-checklisttaken per medewerker (module HR). Afvinkbaar,
 * met een verantwoordelijke. De taken worden uit een sjabloon aangemaakt bij het
 * starten van de in- of uitdiensttreding en zijn daarna aan te vullen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_checklisttaken', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medewerker_id')->constrained('medewerkers')->cascadeOnDelete();
            $table->string('soort', 20); // onboarding | offboarding
            $table->string('titel');
            $table->foreignId('verantwoordelijke_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('volgorde')->default(0);
            $table->boolean('gereed')->default(false);
            $table->timestamp('gereed_op')->nullable();
            $table->foreignId('gereed_door_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['medewerker_id', 'soort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_checklisttaken');
    }
};
