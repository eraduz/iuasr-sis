<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verzendlog voor automatische HR-e-mails (verjaardagsfelicitaties, start van
 * wettelijk verlof). Voorkomt dubbele verzending als de dagelijkse taak vaker
 * draait: uniek per (type, sleutel), bv. "verjaardag" + "12:2026".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_notificaties', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40);   // verjaardag | verlof_start | verlofaanvraag
            $table->string('sleutel');     // idempotentiesleutel, uniek binnen type
            $table->foreignId('medewerker_id')->nullable()->constrained('medewerkers')->nullOnDelete();
            $table->string('ontvanger')->nullable();
            $table->timestamp('verzonden_op')->useCurrent();
            $table->timestamps();

            $table->unique(['type', 'sleutel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_notificaties');
    }
};
