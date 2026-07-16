<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De aanwezigheid per bestuurslid bij een vergadering: fysiek, online of niet
 * bijgewoond. Eén rij per (vergadering, lid).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bestuursvergadering_aanwezigheden', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bestuursvergadering_id')->constrained('bestuursvergaderingen')->cascadeOnDelete();
            $table->foreignId('bestuurslid_id')->constrained('bestuursleden')->cascadeOnDelete();
            $table->string('aanwezigheid', 20);   // Aanwezigheid: fysiek | online | niet_bijgewoond
            $table->timestamps();

            $table->unique(['bestuursvergadering_id', 'bestuurslid_id'], 'bva_verg_lid_uniek');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bestuursvergadering_aanwezigheden');
    }
};
