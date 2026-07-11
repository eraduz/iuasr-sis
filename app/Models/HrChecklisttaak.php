<?php

namespace App\Models;

use App\Enums\ChecklistSoort;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Onboarding-/offboarding-checklisttaak (module HR). */
class HrChecklisttaak extends Model
{
    protected $table = 'hr_checklisttaken';

    protected $fillable = [
        'medewerker_id', 'soort', 'titel', 'verantwoordelijke_id', 'volgorde', 'gereed', 'gereed_op', 'gereed_door_id',
    ];

    protected function casts(): array
    {
        return [
            'soort' => ChecklistSoort::class,
            'volgorde' => 'integer',
            'gereed' => 'boolean',
            'gereed_op' => 'datetime',
        ];
    }

    public function medewerker(): BelongsTo
    {
        return $this->belongsTo(Medewerker::class);
    }

    public function verantwoordelijke(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verantwoordelijke_id');
    }
}
