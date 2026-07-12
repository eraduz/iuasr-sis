<?php

use App\Enums\MedewerkerSoort;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soort medewerker: betaald personeel vs. vrijwilliger. IUASR is een stichting met
 * veel vrijwilligers; die worden wél geregistreerd, maar tellen niet mee in de FTE
 * en worden apart geteld en gefilterd. Bestaande medewerkers zijn 'personeel'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medewerkers', function (Blueprint $table) {
            $table->string('soort')->default(MedewerkerSoort::Personeel->value)->after('personeelsnummer')
                ->comment('personeel | vrijwilliger; vrijwilliger telt niet mee in de FTE');
        });
    }

    public function down(): void
    {
        Schema::table('medewerkers', function (Blueprint $table) {
            $table->dropColumn('soort');
        });
    }
};
