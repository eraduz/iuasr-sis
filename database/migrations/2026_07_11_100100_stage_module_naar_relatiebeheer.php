<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * De placeholder-module 'stage' wordt de volwaardige module
 * Relatiebeheer & Stagebeheer. Deze omvat de stagescholen/werkveldrelaties,
 * contactpersonen, contactmomenten én de stageplaatsen/plaatsingen —
 * opleidingoverstijgend (PABO, Bachelor Islamitische Theologie, Master IGV).
 *
 * We hernoemen de bestaande registry-rij i.p.v. een dubbele module toe te
 * voegen. Werkt op zowel een verse als een bestaande database (guarded).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('modules')->where('sleutel', 'stage')->update([
            'sleutel' => 'relatiebeheer',
            'naam' => 'Relatiebeheer & Stage',
            'omschrijving' => 'Stagescholen en werkveldrelaties, contactpersonen, contactmomenten en stageplaatsen.',
            'icoon' => 'users',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('modules')->where('sleutel', 'relatiebeheer')->update([
            'sleutel' => 'stage',
            'naam' => 'Stage Administratie',
            'omschrijving' => 'Stageplaatsen en -begeleiding.',
            'icoon' => 'cert',
            'updated_at' => now(),
        ]);
    }
};
