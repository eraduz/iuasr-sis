<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Afdelingen (en teams = afdeling met een bovenliggende afdeling) — module HR.
 * `manager_id` verwijst naar een medewerker; die FK wordt in een aparte migratie
 * toegevoegd omdat `medewerkers` pas daarna bestaat (circulaire afhankelijkheid).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('afdelingen', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('naam');
            $table->foreignId('bovenliggende_afdeling_id')->nullable()->constrained('afdelingen')->nullOnDelete();
            $table->unsignedBigInteger('manager_id')->nullable()->index();
            $table->boolean('actief')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('afdelingen');
    }
};
