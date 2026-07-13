<?php

namespace Database\Seeders;

use App\Enums\BibliotheekMailsoort;
use App\Enums\ExemplaarStatus;
use App\Models\Bibliotheek\Publicatiesoort;
use App\Models\Bibliotheek\Auteur;
use App\Models\Bibliotheek\Kast;
use App\Models\Bibliotheek\Publicatie;
use App\Models\Bibliotheek\Reeks;
use App\Models\Bibliotheek\Taal;
use App\Models\Bibliotheek\Uitlening;
use App\Models\Bibliotheek\Vakgebied;
use App\Models\Medewerker;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Synthetische bibliotheekdata (AVG: geen echte titels van echte leners, geen
 * echte persoonsgegevens). Vult de catalogus in vier talen — Arabisch, Turks,
 * Engels en Nederlands — zodat meteen te zien is dat het Unicode-schrift,
 * zoeken en sorteren correct werken.
 *
 * Idempotent: bestaan er al publicaties, dan doet de seeder niets.
 * De talen en vakgebieden komen uit de migratie, niet hieruit.
 */
class BibliotheekSeeder extends Seeder
{
    public function run(): void
    {
        if (Publicatie::query()->exists()) {
            return;
        }

        $taal = fn (string $code) => Taal::where('code', $code)->value('id');
        $soort = fn (string $code) => Publicatiesoort::where('code', $code)->value('id');
        $vak = fn (string $naam) => Vakgebied::where('naam', $naam)->value('id');

        // Boekenkasten.
        $kasten = collect(['A1' => 'Tafsir en Hadith', 'A2' => 'Fiqh en Aqidah', 'B1' => 'Geschiedenis', 'B2' => 'Arabische taal', 'C1' => 'Tijdschriften'])
            ->map(fn ($omschrijving, $code) => Kast::create(['code' => $code, 'omschrijving' => $omschrijving, 'actief' => true]));

        // Boekreeks met vier delen — het voorbeeld uit de opdracht.
        $reeks = Reeks::create([
            'titel' => 'Tafsir Ibn Kathir',
            'opmerking' => 'Klassieke koranexegese, vierdelige uitgave.',
        ]);
        $ibnKathir = Auteur::create(['naam' => 'Ibn Kathir (ابن كثير)']);

        foreach ([1, 2, 3, 4] as $deel) {
            $publicatie = Publicatie::create([
                'soort_id' => $soort('boek'),
                'titel' => 'Tafsir Ibn Kathir',
                'uitgavejaar' => 2018,
                'druknummer' => '2e druk',
                'vakgebied_id' => $vak('Tafsir'),
                'reeks_id' => $reeks->id,
                'deelnummer' => $deel,
            ]);
            $publicatie->auteurs()->sync([$ibnKathir->id]);
            $publicatie->talen()->sync([$taal('ar'), $taal('nl')]);
            $publicatie->exemplaren()->create([
                'serienummer' => 'IUASR-TIK-'.str_pad((string) $deel, 3, '0', STR_PAD_LEFT),
                'kast_id' => $kasten['A1']->id,
                'status' => ExemplaarStatus::Beschikbaar,
            ]);
        }

        // Losse titels in de vier talen.
        $titels = [
            ['صحيح البخاري', 'Sahih al-Bukhari (محمد البخاري)', 'Hadith', ['ar'], 2015, 'A1', 'IUASR-HAD-001', 2],
            ['Riyad as-Salihin', 'An-Nawawi (النووي)', 'Hadith', ['ar', 'nl'], 2019, 'A1', 'IUASR-HAD-002', 1],
            ['İslam Hukukuna Giriş', 'Mehmet Öztürk', 'Fiqh', ['tr'], 2020, 'A2', 'IUASR-FIQ-001', 2],
            ['Fiqh volgens de vier scholen', 'Abdelkarim Yousfi', 'Fiqh', ['nl'], 2021, 'A2', 'IUASR-FIQ-002', 1],
            ['An Introduction to Islamic Theology', 'Sarah Whitfield', 'Aqidah', ['en'], 2017, 'A2', 'IUASR-AQI-001', 1],
            ['Osmanlı Tarihi', 'Ayşe Kaya', 'Geschiedenis', ['tr'], 2016, 'B1', 'IUASR-GES-001', 1],
            ['Geschiedenis van al-Andalus', 'Karim Belkacem', 'Geschiedenis', ['nl', 'en'], 2022, 'B1', 'IUASR-GES-002', 2],
            ['النحو الواضح', 'Ali al-Jarim (علي الجارم)', 'Arabische taal', ['ar'], 2014, 'B2', 'IUASR-ARA-001', 3],
            ['Arabische grammatica voor beginners', 'Laila Haddad', 'Arabische taal', ['nl', 'ar'], 2023, 'B2', 'IUASR-ARA-002', 2],
        ];

        foreach ($titels as [$titel, $auteurNaam, $vakgebied, $talen, $jaar, $kast, $serie, $aantalExemplaren]) {
            $publicatie = Publicatie::create([
                'soort_id' => $soort('boek'),
                'titel' => $titel,
                'uitgavejaar' => $jaar,
                'vakgebied_id' => $vak($vakgebied),
            ]);
            $publicatie->auteurs()->sync(Auteur::idsVoorNamen([$auteurNaam]));
            $publicatie->talen()->sync(array_map($taal, $talen));

            for ($i = 1; $i <= $aantalExemplaren; $i++) {
                $publicatie->exemplaren()->create([
                    'serienummer' => $serie.'-'.$i,
                    'kast_id' => $kasten[$kast]->id,
                    'status' => ExemplaarStatus::Beschikbaar,
                ]);
            }
        }

        // Digitaal document (geen fysieke exemplaren).
        $digitaal = Publicatie::create([
            'soort_id' => $soort('digitaal'),
            'titel' => 'Onderwijsvisie IUASR (PDF)',
            'uitgavejaar' => 2025,
            'vakgebied_id' => $vak('Overige'),
        ]);
        $digitaal->talen()->sync([$taal('nl')]);

        // Tijdschrift met twee uitgaven en artikelen — de zoekvraag uit de opdracht:
        // "in welk tijdschrift staat dit artikel?"
        $tijdschrift = Publicatie::create([
            'soort_id' => $soort('tijdschrift'),
            'titel' => 'Studia Islamica Rotterdam',
            'vakgebied_id' => $vak('Overige'),
        ]);
        $tijdschrift->talen()->sync([$taal('nl'), $taal('en')]);

        $uitgave1 = $tijdschrift->uitgaven()->create([
            'uitgavenummer' => '2025/1',
            'publicatiedatum' => '2025-03-15',
            'jaar' => 2025,
            'locatie' => 'Kast C1',
        ]);
        $uitgave2 = $tijdschrift->uitgaven()->create([
            'uitgavenummer' => '2025/2',
            'publicatiedatum' => '2025-09-01',
            'jaar' => 2025,
            'locatie' => 'Kast C1',
        ]);

        $artikelen = [
            [$uitgave1, 'De rol van de moskee in de Nederlandse wijk', ['Laila Haddad'], '5-21', 'moskee, integratie, wijk'],
            [$uitgave1, 'Tafsir-methodologie in de moderne tijd', ['Karima Nassar', 'Galal Ali'], '22-40', 'tafsir, methodologie, exegese'],
            [$uitgave2, 'Islamic finance in the Netherlands', ['Sarah Whitfield'], '3-19', 'finance, banking, fiqh'],
            [$uitgave2, 'Arabisch onderwijs op de basisschool', ['Laila Haddad'], '20-35', 'arabisch, onderwijs, taalverwerving'],
        ];

        foreach ($artikelen as [$uitgave, $titel, $auteurs, $paginas, $trefwoorden]) {
            $artikel = $uitgave->artikelen()->create([
                'titel' => $titel,
                'paginas' => $paginas,
                'trefwoorden' => $trefwoorden,
                'beschrijving' => 'Synthetisch artikel voor het testen van de zoekfunctie.',
            ]);
            $artikel->auteurs()->sync(Auteur::idsVoorNamen($auteurs));
        }

        // Uitleningen: één lopend, één te laat, één netjes retour — zodat het
        // dashboard, de waarschuwingen en het studentensignaal meteen gevuld zijn.
        $bibliothecaris = User::where('email', 'bibliotheek@iuasr.nl')->first();
        $student = Student::orderBy('id')->first();
        $medewerker = Medewerker::orderBy('id')->first();
        $exemplaren = \App\Models\Bibliotheek\Exemplaar::orderBy('id')->take(3)->get();

        if ($student === null || $exemplaren->count() < 3) {
            return; // Zonder studenten/exemplaren valt er niets uit te lenen.
        }

        // Lopend, binnen de termijn.
        $this->leen($exemplaren[0], $student->id, null, -5, 16, null, $bibliothecaris);

        // Te laat (verwachte retour ligt in het verleden) — vult het te-laat-signaal.
        $this->leen($exemplaren[1], $student->id, null, -40, -12, null, $bibliothecaris);

        // Netjes retour.
        if ($medewerker !== null) {
            $this->leen($exemplaren[2], null, $medewerker->id, -70, -10, -12, $bibliothecaris);
        }
    }

    /** Maakt één uitlening; dagen zijn relatief ten opzichte van vandaag. */
    private function leen(
        \App\Models\Bibliotheek\Exemplaar $exemplaar,
        ?int $studentId,
        ?int $medewerkerId,
        int $uitDagen,
        int $retourDagen,
        ?int $retourOpDagen,
        ?User $door,
    ): void {
        $uitlening = Uitlening::create([
            'exemplaar_id' => $exemplaar->id,
            'student_id' => $studentId,
            'medewerker_id' => $medewerkerId,
            'uitgeleend_op' => Carbon::today()->addDays($uitDagen),
            'verwachte_retour_op' => Carbon::today()->addDays($retourDagen),
            'retour_op' => $retourOpDagen !== null ? Carbon::today()->addDays($retourOpDagen) : null,
            'staat' => $retourOpDagen !== null ? \App\Enums\Materiaalstaat::Goed : null,
            'uitgeleend_door_user_id' => $door?->id,
            'ingenomen_door_user_id' => $retourOpDagen !== null ? $door?->id : null,
        ]);

        $exemplaar->update([
            'status' => $retourOpDagen !== null ? ExemplaarStatus::Beschikbaar : ExemplaarStatus::Uitgeleend,
        ]);

        // Eén synthetische logregel, zodat de e-mailteller op de lenerpagina gevuld is.
        $uitlening->emaillogs()->create([
            'soort' => BibliotheekMailsoort::Uitleenbevestiging,
            'ontvanger' => $uitlening->lenerEmail() ?? 'onbekend@iuasr.nl',
            'cc' => config('sis.mail.cc.bibliotheek'),
            'gelukt' => true,
            'verzonden_op' => Carbon::today()->addDays($uitDagen),
        ]);
    }
}
