<?php

use App\Enums\CijferlijstStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cijferlijst per vak en periode (studiejaar) met de vaststellingsstatus.
 * Stuurt de rolscheiding rond cijfers: wie mag wanneer bewerken.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cijferlijsten', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vak_id')->constrained('vakken')->cascadeOnDelete();
            $table->foreignId('periode_id')->constrained('perioden')->cascadeOnDelete();
            $table->enum('status', array_map(fn ($s) => $s->value, CijferlijstStatus::cases()))
                ->default(CijferlijstStatus::Concept->value);
            $table->timestamp('ingediend_op')->nullable();
            $table->foreignId('ingediend_door_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('vastgesteld_op')->nullable();
            $table->foreignId('vastgesteld_door_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('opmerking')->nullable()->comment('reden bij terugsturen of correctie');
            $table->timestamps();

            $table->unique(['vak_id', 'periode_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cijferlijsten');
    }
};
