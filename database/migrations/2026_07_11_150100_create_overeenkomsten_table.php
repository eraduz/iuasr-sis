<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Overeenkomsten met een organisatie (samenwerkingsovereenkomst, convenant,
 * stagecontract) met een verloopdatum die de signalering 'contracten die
 * verlopen' stuurt. Een getekende PDF wordt gewaarmerkt via de bestaande
 * ondertekenmodule en gekoppeld via `ondertekend_document_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overeenkomsten', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisatie_id')->constrained('organisaties')->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('titel')->nullable();
            $table->date('startdatum')->nullable();
            $table->date('verloopdatum')->nullable();
            $table->string('status', 20)->default('concept');
            $table->foreignId('ondertekend_document_id')->nullable()->constrained('ondertekende_documenten')->nullOnDelete();
            $table->text('opmerking')->nullable();
            $table->timestamps();

            $table->index(['organisatie_id', 'verloopdatum']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overeenkomsten');
    }
};
