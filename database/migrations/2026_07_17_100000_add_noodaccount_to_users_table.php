<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Noodtoegang (break-glass): maximaal TWEE accounts met de rol Beheerder mogen
 * met gebruikersnaam+wachtwoord inloggen als Entra ID (SSO) onbereikbaar is.
 * Alle overige accounts hebben en houden GEEN wachtwoord — die blijven
 * uitsluitend via Entra ID inloggen.
 *
 * Het maximum van twee wordt op DATABASENIVEAU afgedwongen: `noodaccount_slot`
 * is uniek en mag alleen 1 of 2 zijn. MySQL/MariaDB staat meerdere NULL-waarden
 * toe in een unique index, dus reguliere accounts (slot = NULL) zijn onbeperkt,
 * maar er kunnen nooit meer dan twee noodaccounts bestaan — ook niet bij een
 * bug in de applicatie of een handmatige INSERT.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Framework-token: Authenticatable::getAuthPassword() leest `password`.
            // Nullable: reguliere (Entra-)accounts hebben er géén.
            $table->string('password')->nullable()->after('entra_oid');
            $table->unsignedTinyInteger('noodaccount_slot')->nullable()->unique()
                ->after('password')->comment('1 of 2 = noodaccount (break-glass); NULL = regulier account');
            $table->timestamp('wachtwoord_gewijzigd_op')->nullable()->after('laatst_ingelogd_op');
        });

        // Harde bovengrens: alleen slot 1 en 2 bestaan. Samen met de unique index
        // is het maximum van twee noodaccounts hiermee database-afgedwongen.
        DB::statement('ALTER TABLE users ADD CONSTRAINT chk_users_noodaccount_slot CHECK (noodaccount_slot IS NULL OR noodaccount_slot IN (1,2))');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT chk_users_noodaccount_slot');

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['noodaccount_slot']);
            $table->dropColumn(['password', 'noodaccount_slot', 'wachtwoord_gewijzigd_op']);
        });
    }
};
