<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wie heeft de taak afgevinkt? Dat is niet per se degene aan wie zij was
 * toegewezen: een niet-toegewezen taak mag door iedereen bij Studentenzaken
 * worden opgepakt en afgerond.
 *
 * Blijft leeg bij taken die vóór deze wijziging al waren afgerond; dat gegeven
 * is nooit vastgelegd en wordt niet met terugwerkende kracht ingevuld.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('taken', function (Blueprint $table) {
            $table->foreignId('afgerond_door_id')->nullable()->after('afgerond_op')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('taken', function (Blueprint $table) {
            $table->dropConstrainedForeignId('afgerond_door_id');
        });
    }
};
