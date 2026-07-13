<?php

namespace App\Http\Controllers;

use App\Enums\Afstudeerstap;
use App\Enums\InschrijvingStatus;
use App\Enums\Rol;
use App\Models\Afstudeerproces;
use App\Models\Afstudeerprocesstap;
use App\Models\Inschrijving;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Het afstudeerproces (examencommissie-gedreven). De examencommissie start per
 * student in het laatste leerjaar een proces met vijf vaste stappen; elke stap
 * wordt strikt door de verantwoordelijke rol afgevinkt (examencommissie stap 1-3,
 * Studentenzaken stap 4-5). De laatste stap (diploma uitgereikt) studeert de
 * student af. Alles wordt gelogd.
 */
class AfstudeerprocesController extends Controller
{
    /** Kandidatenlijst: studenten in het laatste leerjaar (of met een vervroegd-vrijgave). */
    public function kandidaten(Request $request): View
    {
        $user = $request->user();

        $kandidaten = Inschrijving::query()
            ->whereIn('status', [InschrijvingStatus::Actief->value, InschrijvingStatus::Geschorst->value])
            ->with(['student', 'opleiding', 'periode', 'afstudeerproces.stappen'])
            ->get()
            ->filter(fn (Inschrijving $i) => $i->magAfstuderen()
                && $i->student && $i->student->zichtbaarVoor($user))
            ->sortBy(fn (Inschrijving $i) => ($i->opleiding?->code ?? '').' '.($i->student?->achternaam ?? ''))
            ->values();

        return view('afstuderen.kandidaten', compact('kandidaten'));
    }

    /** De examencommissie start het afstudeerproces voor een inschrijving. */
    public function start(Request $request, Inschrijving $inschrijving): RedirectResponse
    {
        abort_unless($this->magStarten($request->user()), 403);
        abort_unless($inschrijving->magAfstuderen(), 422, 'Deze inschrijving is niet afstudeerbaar (geen laatste leerjaar of vrijgave).');

        if ($inschrijving->afstudeerproces()->exists()) {
            return back()->with('status', 'Er loopt al een afstudeerproces voor deze inschrijving.');
        }

        $proces = Afstudeerproces::create([
            'inschrijving_id' => $inschrijving->id,
            'student_id' => $inschrijving->student_id,
            'gestart_door_id' => $request->user()->id,
            'gestart_op' => now(),
            'status' => Afstudeerproces::LOPEND,
        ]);

        foreach (Afstudeerstap::inVolgorde() as $stap) {
            $proces->stappen()->create(['stap' => $stap->value, 'volgorde' => $stap->volgorde()]);
        }

        AuditLogger::log(AuditLogger::AANMAAK, $inschrijving->student, veld: 'afstudeerproces', context: [
            'inschrijving_id' => $inschrijving->id,
            'opleiding' => $inschrijving->opleiding?->code,
        ]);

        return redirect()->route('studenten.show', $inschrijving->student_id)
            ->with('status', 'Afstudeerproces gestart.');
    }

    /** Een stap afvinken of heropenen (strikt per rol, sequentieel). */
    public function stapAfvinken(Request $request, Afstudeerprocesstap $stap): RedirectResponse
    {
        $stap->load('proces.inschrijving.opleiding', 'proces.stappen', 'proces.student');
        $proces = $stap->proces;
        $stapEnum = $stap->stap;

        abort_unless($this->magStapMuteren($request->user(), $stapEnum), 403, 'Deze stap hoort bij een andere rol.');

        if (! $proces->isLopend()) {
            return back()->with('status', 'Het afstudeerproces is afgerond of afgebroken; stappen zijn niet meer wijzigbaar.');
        }

        $stappen = $proces->stappen->sortBy('volgorde')->values();
        $index = $stappen->search(fn ($s) => $s->id === $stap->id);
        $wordtGereed = ! $stap->gereed;

        if ($wordtGereed) {
            $vorige = $index > 0 ? $stappen->get($index - 1) : null;
            if ($vorige && ! $vorige->gereed) {
                return back()->with('status', 'Rond eerst de vorige stap af — de stappen worden op volgorde doorlopen.');
            }
        } else {
            $volgende = $stappen->get($index + 1);
            if ($volgende && $volgende->gereed) {
                return back()->with('status', 'Heropen eerst de volgende stap.');
            }
        }

        $opmerking = $request->validate(['opmerking' => ['nullable', 'string', 'max:255']])['opmerking'] ?? $stap->opmerking;

        $stap->update([
            'gereed' => $wordtGereed,
            'gereed_op' => $wordtGereed ? now() : null,
            'gereed_door_id' => $wordtGereed ? $request->user()->id : null,
            'opmerking' => $opmerking,
        ]);

        AuditLogger::log(AuditLogger::WIJZIGING, $proces->student, veld: 'afstudeerstap', context: [
            'inschrijving_id' => $proces->inschrijving_id,
            'stap' => $stapEnum->value,
            'gereed' => $wordtGereed,
        ]);

        // De afrondende stap (diploma uitgereikt) studeert de student af.
        if ($wordtGereed && $stapEnum->isAfrondend()) {
            $this->rondAf($proces, $request->user());

            return back()->with('status', 'Diploma uitgereikt — de student is afgestudeerd (alumnus).');
        }

        return back()->with('status', $wordtGereed ? 'Stap afgevinkt.' : 'Stap heropend.');
    }

    /** De examencommissie breekt een lopend proces af. */
    public function afbreken(Request $request, Afstudeerproces $proces): RedirectResponse
    {
        abort_unless($this->magStarten($request->user()), 403);

        if ($proces->isAfgerond()) {
            return back()->with('status', 'Een afgerond proces kan niet worden afgebroken.');
        }

        $proces->update(['status' => Afstudeerproces::AFGEBROKEN]);
        AuditLogger::log(AuditLogger::WIJZIGING, $proces->student, veld: 'afstudeerproces', context: [
            'inschrijving_id' => $proces->inschrijving_id, 'status' => 'afgebroken',
        ]);

        return back()->with('status', 'Afstudeerproces afgebroken.');
    }

    /** Rondt het proces af: zet de inschrijving op afgestudeerd (hergebruikt de afstuderen-logica). */
    private function rondAf(Afstudeerproces $proces, User $user): void
    {
        $inschrijving = $proces->inschrijving;
        if ($inschrijving && $inschrijving->magAfstuderen()) {
            $inschrijving->update([
                'status' => InschrijvingStatus::Afgestudeerd,
                'afstudeerdatum' => now()->toDateString(),
            ]);
            AuditLogger::log(AuditLogger::WIJZIGING, $proces->student, veld: 'afstuderen', context: [
                'inschrijving_id' => $inschrijving->id,
                'opleiding' => $inschrijving->opleiding?->code,
                'via' => 'afstudeerproces',
            ]);
        }

        $proces->update(['status' => Afstudeerproces::AFGEROND, 'afgerond_op' => now()]);
    }

    /** Starten/afbreken: de examencommissie (of Beheer). */
    private function magStarten(User $user): bool
    {
        return $user->heeftRol(Rol::Examencommissie) || $user->heeftRol(Rol::Beheerder);
    }

    /** Een stap muteren mag alleen de verantwoordelijke rol (of Beheer). */
    private function magStapMuteren(User $user, Afstudeerstap $stap): bool
    {
        return $stap->magAfvinkenDoor($user);
    }
}
