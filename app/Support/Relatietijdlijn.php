<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Organisatie;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Bouwt de gecombineerde historie/tijdlijn van een organisatie (module
 * Relatiebeheer & Stagebeheer): contactmomenten, notities en de audit-log-
 * mutaties op de organisatie en haar contactpersonen, chronologisch (nieuwste
 * eerst). De tijdlijn wordt afgeleid — er is geen aparte historietabel.
 */
class Relatietijdlijn
{
    /**
     * @return Collection<int, array{moment: Carbon, soort: string, label: string, titel: string, detail: ?string, door: ?string}>
     */
    public static function voor(Organisatie $organisatie): Collection
    {
        $items = collect();

        foreach ($organisatie->contactmomenten as $cm) {
            $items->push([
                'moment' => $cm->datum,
                'soort' => 'contactmoment',
                'label' => $cm->type?->naam ?? 'Contactmoment',
                'titel' => $cm->onderwerp,
                'detail' => $cm->samenvatting,
                'door' => $cm->medewerker?->naam,
            ]);
        }

        foreach ($organisatie->notities as $n) {
            $items->push([
                'moment' => $n->created_at,
                'soort' => 'notitie',
                'label' => 'Notitie'.($n->categorie ? ' · '.$n->categorie : ''),
                'titel' => \Illuminate\Support\Str::limit($n->tekst, 80),
                'detail' => $n->tags ? 'Tags: '.$n->tags : null,
                'door' => $n->auteur?->naam,
            ]);
        }

        $contactpersoonIds = $organisatie->contactpersonen->pluck('id')->all();
        $logs = AuditLog::query()
            ->where(function ($q) use ($organisatie, $contactpersoonIds) {
                $q->where(fn ($s) => $s->where('onderwerp_type', 'Organisatie')->where('onderwerp_id', $organisatie->id));
                if (! empty($contactpersoonIds)) {
                    $q->orWhere(fn ($s) => $s->where('onderwerp_type', 'Contactpersoon')->whereIn('onderwerp_id', $contactpersoonIds));
                }
            })
            ->orderByDesc('gelogd_op')->limit(50)->get();

        foreach ($logs as $log) {
            $items->push([
                'moment' => Carbon::parse($log->gelogd_op),
                'soort' => 'mutatie',
                'label' => 'Wijziging ('.$log->onderwerp_type.')',
                'titel' => ucfirst((string) $log->actie).($log->veld ? ' — '.$log->veld : ''),
                'detail' => null,
                'door' => $log->rol,
            ]);
        }

        return $items
            ->sortByDesc(fn ($i) => optional($i['moment'])->timestamp ?? 0)
            ->values();
    }
}
