<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Begeleidingsgesprekken binnen een scriptietraject (stap 6). De student en de
 * begeleider registreren per gesprek datum/tijd, besproken punten, feedback,
 * afspraken en actiepunten. `status` is een sleutel uit ScriptieGesprek::STATUSSEN;
 * de wederzijdse bevestiging staat als twee booleans vast.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scriptie_gesprekken', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scriptie_id')->constrained('scripties')->cascadeOnDelete();
            $table->date('datum');
            $table->time('begintijd')->nullable();
            $table->time('eindtijd')->nullable();
            $table->string('locatie')->nullable();
            $table->boolean('online')->default(false);
            $table->string('onderwerp')->nullable();
            $table->text('besproken')->nullable();
            $table->text('feedback')->nullable();
            $table->text('afspraken')->nullable();
            $table->text('actiepunten_student')->nullable();
            $table->text('actiepunten_begeleider')->nullable();
            $table->date('actiepunten_deadline')->nullable();
            $table->string('status', 30)->default('gepland'); // ScriptieGesprek::STATUSSEN
            $table->boolean('bevestigd_student')->default(false);
            $table->boolean('bevestigd_begeleider')->default(false);
            $table->foreignId('geregistreerd_door_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['scriptie_id', 'datum']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scriptie_gesprekken');
    }
};
