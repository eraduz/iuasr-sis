<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ziek- en herstelmeldingen (module HR). Een open melding (zonder hersteldatum)
 * betekent dat de medewerker ziek is; de verzuimrapportage wordt hieruit afgeleid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ziekmeldingen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medewerker_id')->constrained('medewerkers')->cascadeOnDelete();
            $table->date('ziek_van');
            $table->date('hersteld_op')->nullable();
            $table->unsignedTinyInteger('percentage')->default(100);
            $table->text('opmerking')->nullable();
            $table->foreignId('gemeld_door_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['medewerker_id', 'ziek_van']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ziekmeldingen');
    }
};
