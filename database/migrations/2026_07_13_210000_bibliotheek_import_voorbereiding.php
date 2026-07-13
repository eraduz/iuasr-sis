<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Voorbereiding op de import van de bestaande Excel-bibliotheek.
 *
 * 1. VAKGEBIED PER REKLETTER. In de bronbestanden is de kolom 'vakgebied'
 *    vervuild (144 spellingvarianten van dezelfde begrippen). De REKCODE is dat
 *    niet: die begint met een letter (A-1, F.143) en het werkblad 'Alfabet boek'
 *    koppelt elke letter aan een vakgebied. De letter wordt daarom de bron van
 *    waarheid; daarvoor krijgt `bibliotheek_vakgebieden` een kolom `rekletter`.
 *
 * 2. KASTEN A t/m U — de rekletter is tevens de boekenkast.
 *
 * 3. EXTRA TALEN. De collectie bevat naast Arabisch, Turks, Engels en Nederlands
 *    ook Frans, Duits, Spaans en Albanees.
 *
 * 4. HERKOMST. `bron_rekcode` op de publicatie legt vast uit welke regel van het
 *    Excel-bestand een titel komt. Daarmee is de import IDEMPOTENT: een tweede
 *    keer draaien slaat de al ingelezen regels over.
 */
return new class extends Migration
{
    /** De legenda uit het werkblad 'Alfabet boek', aangevuld met de bladen T en U. */
    private const LEGENDA = [
        'A' => ['Tafsir (exegese)', 'التفسير'],
        'B' => ['Quran wetenschappen', 'علوم القرآن'],
        'C' => ['Tijdschriften', 'المجلات الإسلامية'],
        'D' => ['Hadith en overleveraars', 'الحديث و أسماء الرجال'],
        'E' => ['Biografie van de Profeet', 'السيرة النبوية'],
        'F' => ['Fiqh (jurisprudentie)', 'الفقه'],
        'G' => ['Taal en literatuur', 'اللغة'],
        'H' => ['Geschiedenis', 'التأريخ'],
        'I' => ['Islamitische boeken', 'كتب إسلامية'],
        'J' => ['Geloofsleer (Aqidah)', 'العقيدة'],
        'K' => ['Mystiek (Tasawwuf)', 'التصوف'],
        'L' => ['Godsdiensten', 'الأديان'],
        'M' => ['Sjiitische boeken', 'كتب شيعية'],
        'N' => ['Encyclopedieën', 'الموسوعات'],
        'O' => ['Psychologie', 'علم النفس'],
        'P' => ['Sociologie', 'علم الاجتماع'],
        'Q' => ['Onderwijs en pedagogiek', 'التربية والتعليم'],
        'R' => ['Taal- en schrijfvaardigheid', 'كتب اللغة والمهارات'],
        'S' => ['Kalligrafie en kunst', 'علم الخط'],
        'T' => ['Tijdschriften (Engels/Nederlands)', 'المجلات'],
        'U' => ['Filosofie', 'الفلسفة'],
    ];

    public function up(): void
    {
        Schema::table('bibliotheek_vakgebieden', function (Blueprint $table) {
            $table->char('rekletter', 1)->nullable()->unique()->after('naam');
        });

        Schema::table('bibliotheek_publicaties', function (Blueprint $table) {
            // Herkomst uit het Excel-bestand (bijv. "F. 143"); leeg bij handmatige invoer.
            $table->string('bron_rekcode', 40)->nullable()->after('opmerking');
            $table->index('bron_rekcode');
        });

        $nu = now();
        $volgorde = 0;

        foreach (self::LEGENDA as $letter => [$naam, $arabisch]) {
            $volgorde++;

            // Bestaat er al een vakgebied met deze naam (uit de eerste migratie),
            // dan krijgt dat de letter; anders komt er een nieuw vakgebied bij.
            $bestaand = DB::table('bibliotheek_vakgebieden')->where('naam', $naam)->first();

            if ($bestaand !== null) {
                DB::table('bibliotheek_vakgebieden')->where('id', $bestaand->id)
                    ->update(['rekletter' => $letter, 'omschrijving' => $arabisch, 'updated_at' => $nu]);

                continue;
            }

            DB::table('bibliotheek_vakgebieden')->insert([
                'naam' => $naam,
                'rekletter' => $letter,
                'omschrijving' => $arabisch,
                'actief' => true,
                'volgorde' => $volgorde,
                'created_at' => $nu,
                'updated_at' => $nu,
            ]);

            // De boekenkast draagt dezelfde letter.
            if (! DB::table('bibliotheek_kasten')->where('code', $letter)->exists()) {
                DB::table('bibliotheek_kasten')->insert([
                    'code' => $letter,
                    'omschrijving' => 'Rek '.$letter.' — '.$naam,
                    'actief' => true,
                    'created_at' => $nu,
                    'updated_at' => $nu,
                ]);
            }
        }

        // Kasten voor de letters die al een vakgebied hadden (Tafsir, Hadith, ...).
        foreach (array_keys(self::LEGENDA) as $letter) {
            if (! DB::table('bibliotheek_kasten')->where('code', $letter)->exists()) {
                DB::table('bibliotheek_kasten')->insert([
                    'code' => $letter,
                    'omschrijving' => 'Rek '.$letter,
                    'actief' => true,
                    'created_at' => $nu,
                    'updated_at' => $nu,
                ]);
            }
        }

        // Extra talen die in de collectie voorkomen.
        foreach (['fr' => 'Frans', 'de' => 'Duits', 'es' => 'Spaans', 'sq' => 'Albanees'] as $code => $naam) {
            if (! DB::table('bibliotheek_talen')->where('code', $code)->exists()) {
                DB::table('bibliotheek_talen')->insert([
                    'code' => $code,
                    'naam' => $naam,
                    'actief' => true,
                    'created_at' => $nu,
                    'updated_at' => $nu,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('bibliotheek_publicaties', function (Blueprint $table) {
            $table->dropIndex(['bron_rekcode']);
            $table->dropColumn('bron_rekcode');
        });

        Schema::table('bibliotheek_vakgebieden', function (Blueprint $table) {
            $table->dropColumn('rekletter');
        });

        DB::table('bibliotheek_talen')->whereIn('code', ['fr', 'de', 'es', 'sq'])->delete();
    }
};
