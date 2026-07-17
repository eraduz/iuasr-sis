<?php

use App\Enums\Meldingniveau;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Systeemmeldingen: een balk bovenaan ELKE pagina van elke module, waarmee de
 * Beheerder iets aan alle medewerkers kwijt kan ("vandaag onderhoud vanaf 18:00").
 *
 * ZICHTBAARHEID IS AFGELEID, GEEN KOLOM: een melding staat er zolang `now()`
 * tussen `van` en `tot` valt. Dat is bewust, en volgt de lijn van de rest van dit
 * systeem (te-late taken, lopende betalingsafspraken, isLopend()). Het scheelt
 * een achtergrondtaak — er is geen cron nodig om de melding weg te halen, hij
 * verdwijnt vanzelf op de seconde — en er kan nooit een melding blijven hangen
 * doordat een geplande taak niet liep. `tot` staat standaard op een dag na `van`
 * (config sis.melding.standaard_duur_uren), precies zoals gevraagd.
 *
 * De rij zelf blijft na `tot` bestaan, zodat het overzicht laat zien wat er is
 * omgeroepen. Het commando `sis:meldingen-opruimen` verwijdert ze pas veel later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meldingen', function (Blueprint $table) {
            $table->id(); // surrogaatsleutel
            $table->enum('niveau', Meldingniveau::waarden())->index();
            $table->string('titel');
            $table->text('tekst');
            // Samen bepalen deze twee of de melding NU zichtbaar is. `van` mag in
            // de toekomst liggen: onderhoud kan zo vooruit klaargezet worden.
            $table->timestamp('van')->index();
            $table->timestamp('tot')->index();
            // NULL of leeg = iedereen. Anders alleen deze rolsleutels; zo kan een
            // bericht over de bibliotheek de docenten met rust laten.
            $table->json('rollen')->nullable();
            $table->boolean('afsluitbaar')->default(true)
                ->comment('Mag de medewerker de melding wegklikken? Urgent standaard niet.');
            $table->foreignId('aangemaakt_door_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meldingen');
    }
};
