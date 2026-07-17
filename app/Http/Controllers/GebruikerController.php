<?php

namespace App\Http\Controllers;

use App\Enums\Rol;
use App\Models\Opleiding;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;

/**
 * Gebruikers & rollen (Beheerder). De toegangsmatrix maakt zichtbaar wat elke
 * rol mag; het aanmaken en wijzigen van gebruikers wordt gelogd. Authenticatie
 * zelf loopt via Entra ID — hier worden geen wachtwoorden beheerd.
 *
 * Multi-rol: elke gebruiker heeft één PRIMAIRE rol (`users.rol`, bepaalt het
 * startdashboard en de weergave) plus optioneel EXTRA rollen. De rechten zijn de
 * unie van alle rollen; risicocombinaties (bijv. Studentenzaken + cijferinzage)
 * worden gemeld en gelogd, niet geblokkeerd.
 */
class GebruikerController extends Controller
{
    public function index(): View
    {
        $gebruikers = User::with(['opleidingen', 'gedirigeerdeCursussen', 'rolToewijzingen'])
            ->orderBy('rol')->orderBy('naam')->get();
        $rollen = Rol::cases();

        // Directieleden krijgen per opleiding toegewezen wat zij mogen zien.
        $directie = $gebruikers->filter(fn (User $g) => $g->heeftRol(Rol::Directie))->values();
        $opleidingen = Opleiding::where('actief', true)->orderBy('naam')->get();

        return view('gebruikers.index', compact('gebruikers', 'rollen', 'directie', 'opleidingen'));
    }

    /** Nieuwe gebruiker aanmaken (Beheerder). Geen wachtwoord: login via Entra/dev-login. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'naam' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'rol' => ['required', new Enum(Rol::class)],
            'rollen' => ['array'],
            'rollen.*' => [new Enum(Rol::class)],
            'actief' => ['sometimes', 'boolean'],
        ]);

        $primair = Rol::from($data['rol']);
        $extra = $this->extraRollenUit($data['rollen'] ?? [], $primair);

        $gebruiker = User::create([
            'naam' => $data['naam'],
            'email' => $data['email'],
            'rol' => $primair,
            'actief' => $request->boolean('actief', true),
        ]);
        $this->syncExtraRollen($gebruiker, $extra);

        AuditLogger::log(AuditLogger::AANMAAK, $gebruiker, veld: 'gebruiker', context: [
            'rol' => $primair->value,
            'extra_rollen' => array_map(fn (Rol $r) => $r->value, $extra),
        ]);

        $status = 'Gebruiker aangemaakt: '.$gebruiker->naam.'. Inloggen kan zodra het e-mailadres in Entra ID bestaat.';
        if ($waarschuwing = $this->risicoWaarschuwing($primair, $extra)) {
            $status .= ' '.$waarschuwing;
        }

        return redirect()->route('gebruikers')->with('status', $status);
    }

    /** Wijs een directielid toe aan één of meer opleidingen (zichtbaarheidsgrens). */
    public function updateOpleidingen(Request $request, User $gebruiker): RedirectResponse
    {
        abort_unless($gebruiker->heeftRol(Rol::Directie), 403,
            'Opleidingtoewijzing geldt alleen voor de rol Directie.');

        $data = $request->validate([
            'opleidingen' => ['array'],
            'opleidingen.*' => ['integer', 'exists:opleidingen,id'],
        ]);

        $ids = $data['opleidingen'] ?? [];
        $gebruiker->opleidingen()->sync($ids);

        AuditLogger::log(AuditLogger::WIJZIGING, $gebruiker, veld: 'opleidingtoewijzing', context: [
            'opleiding_ids' => array_values($ids),
        ]);

        return redirect()->route('gebruikers')
            ->with('status', 'Opleidingtoewijzing bijgewerkt voor '.$gebruiker->naam.'.');
    }

    public function updateRol(Request $request, User $gebruiker): RedirectResponse
    {
        $data = $request->validate([
            'rol' => ['required', new Enum(Rol::class)],
            'rollen' => ['array'],
            'rollen.*' => [new Enum(Rol::class)],
            'actief' => ['sometimes', 'boolean'],
        ]);

        $primair = Rol::from($data['rol']);
        $extra = $this->extraRollenUit($data['rollen'] ?? [], $primair);

        // Een noodaccount (break-glass) mag hier niet stilzwijgend onbruikbaar
        // worden. Zou de rol Beheerder wegvallen of het account inactief worden,
        // dan blijft `noodaccount_slot` bezet terwijl inloggen niet meer lukt: het
        // scherm meldt dan '2 van 2 plaatsen in gebruik' terwijl er nog maar één
        // werkt — en dat merkt u pas als Entra ID daadwerkelijk uitvalt. Trek de
        // noodtoegang daarom eerst bewust in.
        if ($gebruiker->isNoodaccount()) {
            $blijftBeheerder = $primair === Rol::Beheerder || in_array(Rol::Beheerder, $extra, true);
            $blijftActief = $request->boolean('actief', $gebruiker->actief);

            if (! $blijftBeheerder || ! $blijftActief) {
                return back()->with('fout', "{$gebruiker->naam} is noodaccount {$gebruiker->noodaccount_slot} en moet daarvoor een actieve Beheerder blijven. Trek eerst de noodtoegang in via Beheer → Noodaccounts.");
            }
        }

        $oudPrimair = $gebruiker->rol->value;
        $oudExtra = $gebruiker->extraRollen()->map(fn (Rol $r) => $r->value)->all();

        $gebruiker->update([
            'rol' => $primair,
            'actief' => $request->boolean('actief', $gebruiker->actief),
        ]);
        $this->syncExtraRollen($gebruiker, $extra);

        AuditLogger::log(AuditLogger::WIJZIGING, $gebruiker, veld: 'rol', context: [
            'van' => $oudPrimair,
            'naar' => $primair->value,
            'extra_van' => $oudExtra,
            'extra_naar' => array_map(fn (Rol $r) => $r->value, $extra),
        ]);

        $status = 'Gebruiker bijgewerkt: '.$gebruiker->naam.'.';
        if ($waarschuwing = $this->risicoWaarschuwing($primair, $extra)) {
            $status .= ' '.$waarschuwing;
        }

        return redirect()->route('gebruikers')->with('status', $status);
    }

    /**
     * Zet de opgegeven rolsleutels om naar extra rollen: ontdubbeld en zonder de
     * primaire rol (die staat immers al op `users.rol`).
     *
     * @param  array<int, string>  $sleutels
     * @return array<int, Rol>
     */
    private function extraRollenUit(array $sleutels, Rol $primair): array
    {
        return collect($sleutels)
            ->map(fn (string $s) => Rol::from($s))
            ->reject(fn (Rol $r) => $r === $primair)
            ->unique(fn (Rol $r) => $r->value)
            ->values()
            ->all();
    }

    /** @param  array<int, Rol>  $extra */
    private function syncExtraRollen(User $gebruiker, array $extra): void
    {
        $gebruiker->rolToewijzingen()->delete();
        foreach ($extra as $rol) {
            $gebruiker->rolToewijzingen()->create(['rol' => $rol->value]);
        }
        $gebruiker->unsetRelation('rolToewijzingen');
    }

    /**
     * Waarschuwing bij een rolcombinatie die de niet-onderhandelbare rolscheiding
     * doorbreekt: Studentenzaken hoort GEEN cijfers te zien, maar krijgt via een
     * cijferrol (Docent/Examencommissie/Directie) toch inzage. De combinatie wordt
     * toegestaan (soms nodig), maar bewust gemeld.
     *
     * @param  array<int, Rol>  $extra
     */
    private function risicoWaarschuwing(Rol $primair, array $extra): ?string
    {
        $rollen = collect([$primair])->merge($extra);
        $cijferRollen = [Rol::Docent, Rol::Examencommissie, Rol::Directie];

        if ($rollen->contains(Rol::Studentenzaken)
            && $rollen->contains(fn (Rol $r) => in_array($r, $cijferRollen, true))) {
            return 'Let op: deze combinatie geeft Studentenzaken cijferinzage, wat normaal strikt gescheiden is. De keuze is gelogd.';
        }

        return null;
    }
}
