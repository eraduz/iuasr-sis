<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nu `medewerkers` bestaat, kan de FK afdelingen.manager_id → medewerkers worden
 * toegevoegd (afdelingshoofd). SET NULL bij verwijderen van de medewerker.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('afdelingen', function (Blueprint $table) {
            $table->foreign('manager_id')->references('id')->on('medewerkers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('afdelingen', function (Blueprint $table) {
            $table->dropForeign(['manager_id']);
        });
    }
};
