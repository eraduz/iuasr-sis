<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Korting op het collegegeld van één inschrijving, bedoeld voor een tweede
 * opleiding maar bruikbaar voor elke afgesproken korting.
 *
 * BELEIDSWIJZIGING (opdrachtgever, 2026-07-10): collegegeld wordt voortaan PER
 * OPLEIDING geheven, niet één keer per studiejaar. Elke inschrijving krijgt dus
 * een eigen termijnschema en eigen facturen; op de tweede opleiding kan een
 * korting worden gegeven.
 *
 * Het percentage wordt door Studentenzaken vastgelegd; het systeem leidt nooit
 * zelf af welke opleiding 'de tweede' is. Een korting boven 0% vereist een
 * reden, zodat achteraf navolgbaar is waarom er minder is gefactureerd.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inschrijvingen', function (Blueprint $table) {
            $table->decimal('korting_percentage', 5, 2)->default(0)->after('betaalregeling')
                ->comment('0.00 t/m 100.00; korting op het jaartarief van deze inschrijving');
            $table->string('korting_reden', 120)->nullable()->after('korting_percentage');
        });
    }

    public function down(): void
    {
        Schema::table('inschrijvingen', function (Blueprint $table) {
            $table->dropColumn(['korting_percentage', 'korting_reden']);
        });
    }
};
