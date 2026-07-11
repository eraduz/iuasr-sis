<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * EC-model per opleiding: bepaalt hoe een voldoende leidt tot de vak-EC.
 *  - 'knockout'        : elk meetellend toetsonderdeel moet ≥ cesuur zijn.
 *  - 'compensatorisch' : het gewogen eindcijfer moet ≥ cesuur zijn.
 * Nullable = terugval op config('sis.cijfers.ec_model'). De bindende regel staat
 * in het OER (studiegids-analyse 2026-07-11); daarom data-gedreven en TE BEVESTIGEN
 * per opleiding, net als voldoende_grens en ec_overgang_drempel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opleidingen', function (Blueprint $table) {
            $table->string('ec_model', 20)->nullable()->after('ec_overgang_drempel')
                ->comment('knockout | compensatorisch; null = config-terugval. TE BEVESTIGEN per OER');
        });
    }

    public function down(): void
    {
        Schema::table('opleidingen', function (Blueprint $table) {
            $table->dropColumn('ec_model');
        });
    }
};
