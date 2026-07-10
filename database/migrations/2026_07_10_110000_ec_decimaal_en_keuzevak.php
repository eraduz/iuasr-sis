<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Halve studiepunten. Het IUASR-curriculum kent vakken van 2,5 EC (o.a. de
 * Standaard Arabisch-reeks en Qoranrecitatie). Een geheel getal zou die
 * stilzwijgend naar beneden afronden en de jaartotalen laten afwijken.
 *
 * Tevens: `keuzevak`. Vakken uit de keuzeruimte (bachelor jaar 4) worden NIET
 * automatisch aan een inschrijving toegewezen; Studentenzaken kent ze per
 * student toe. Zonder deze markering zou elke vierdejaars de volledige
 * keuzeruimte op zijn dossier krijgen (95 EC i.p.v. 40 EC verplicht).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vakken', function (Blueprint $table) {
            $table->decimal('ec', 4, 1)->change();
            $table->boolean('keuzevak')->default(false)->after('blok')
                ->comment('true = keuzeruimte; niet automatisch toewijzen');
        });

        Schema::table('vaktoewijzingen', function (Blueprint $table) {
            $table->decimal('vrijstelling_ec', 4, 1)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('vaktoewijzingen', function (Blueprint $table) {
            $table->unsignedSmallInteger('vrijstelling_ec')->nullable()->change();
        });

        Schema::table('vakken', function (Blueprint $table) {
            $table->dropColumn('keuzevak');
            $table->unsignedSmallInteger('ec')->change();
        });
    }
};
