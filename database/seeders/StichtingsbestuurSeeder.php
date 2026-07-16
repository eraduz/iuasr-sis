<?php

namespace Database\Seeders;

use App\Enums\Aanwezigheid;
use App\Enums\Bestuursorgaan;
use App\Enums\Bestuurstitel;
use App\Models\Bestuurslid;
use App\Models\Bestuursvergadering;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Module Stichtingsbestuur — synthetische seed (AVG-veilig, verzonnen personen).
 * Vult het Stichtingsbestuur en de Raad van Toezicht en legt één voorbeeldvergadering
 * met aanwezigheid vast. Idempotent.
 */
class StichtingsbestuurSeeder extends Seeder
{
    public function run(): void
    {
        if (Bestuurslid::query()->exists()) {
            return;
        }

        $B = Bestuursorgaan::Stichtingsbestuur->value;
        $R = Bestuursorgaan::RaadVanToezicht->value;

        $leden = [
            [$B, Bestuurstitel::Voorzitter, 'Ibrahim', 'Öztürk', 'Eindverantwoordelijk; vertegenwoordigt de stichting'],
            [$B, Bestuurstitel::Penningmeester, 'Fatima', 'El Idrissi', 'Financieel beheer en begroting'],
            [$B, Bestuurstitel::Secretaris, 'Yusuf', 'Demir', 'Notulen, correspondentie en archief'],
            [$B, Bestuurstitel::Lid, 'Aisha', 'Bakkali', 'Algemeen bestuurslid'],
            [$R, Bestuurstitel::Voorzitter, 'Mohammed', 'Amrani', null],
            [$R, Bestuurstitel::Commissaris, 'Khadija', 'Yilmaz', null],
            [$R, Bestuurstitel::Commissaris, 'Omar', 'Chakir', null],
        ];

        foreach ($leden as [$orgaan, $titel, $voornaam, $achternaam, $bevoegdheid]) {
            Bestuurslid::create([
                'orgaan' => $orgaan,
                'titel' => $titel->value,
                'voornaam' => $voornaam,
                'achternaam' => $achternaam,
                'email' => strtolower($voornaam.'.'.str_replace([' ', 'ö', 'ü'], ['', 'o', 'u'], $achternaam)).'@voorbeeld.nl',
                'telefoon' => '06-1000'.random_int(1000, 9999),
                'datum_in_functie' => '2023-01-01',
                'bevoegdheid' => $bevoegdheid,
                'actief' => true,
            ]);
        }

        $notulist = User::where('email', 'stichtingsbestuur@iuasr.nl')->first();

        $vergadering = Bestuursvergadering::create([
            'datum' => '2026-03-12',
            'orgaan' => $B,
            'locatie' => 'Bestuurskamer IUASR, Rotterdam',
            'onderwerpen' => "Vaststelling jaarrekening 2025\nBegroting 2026\nVoortgang huisvesting",
            'besluiten' => "De jaarrekening 2025 is vastgesteld.\nDe begroting 2026 is goedgekeurd.",
            'genotuleerd_door_id' => $notulist?->id,
        ]);

        // Aanwezigheid voor de bestuursleden.
        $bestuur = Bestuurslid::voorOrgaan(Bestuursorgaan::Stichtingsbestuur)->get();
        $standen = [Aanwezigheid::Fysiek, Aanwezigheid::Fysiek, Aanwezigheid::Online, Aanwezigheid::NietBijgewoond];
        foreach ($bestuur as $i => $lid) {
            $vergadering->aanwezigheden()->create([
                'bestuurslid_id' => $lid->id,
                'aanwezigheid' => ($standen[$i] ?? Aanwezigheid::Fysiek)->value,
            ]);
        }
    }
}
