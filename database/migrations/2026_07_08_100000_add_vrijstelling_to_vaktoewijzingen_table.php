<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vrijstelling per toegewezen vak (administratief). Een vrijstelling wordt
 * formeel VERLEEND door de examencommissie; Studentenzaken REGISTREERT het
 * besluit hier (referentie + datum). Het is GEEN cijfer: er zijn EC behaald
 * zonder numeriek eindcijfer (vermelding "VR" op de cijferlijst). Zie CLAUDE.md
 * rolscheiding: SZ registreert de administratieve status, muteert geen cijfers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vaktoewijzingen', function (Blueprint $table) {
            $table->boolean('vrijgesteld')->default(false)->after('automatisch');
            $table->string('vrijstelling_grondslag', 30)->nullable()->after('vrijgesteld')
                ->comment('vooropleiding|evc|eerder_behaald|overig');
            $table->string('vrijstelling_besluit', 120)->nullable()->after('vrijstelling_grondslag')
                ->comment('referentie van het examencommissie-besluit');
            $table->date('vrijstelling_besluit_datum')->nullable()->after('vrijstelling_besluit');
            $table->text('vrijstelling_toelichting')->nullable()->after('vrijstelling_besluit_datum');
            $table->unsignedSmallInteger('vrijstelling_ec')->nullable()->after('vrijstelling_toelichting')
                ->comment('toegekende EC bij de vrijstelling (= vak-EC op moment van vastleggen)');
            $table->foreignId('vrijgesteld_door_id')->nullable()->after('vrijstelling_ec')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('vrijgesteld_op')->nullable()->after('vrijgesteld_door_id');
        });
    }

    public function down(): void
    {
        Schema::table('vaktoewijzingen', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vrijgesteld_door_id');
            $table->dropColumn([
                'vrijgesteld', 'vrijstelling_grondslag', 'vrijstelling_besluit',
                'vrijstelling_besluit_datum', 'vrijstelling_toelichting', 'vrijstelling_ec', 'vrijgesteld_op',
            ]);
        });
    }
};
