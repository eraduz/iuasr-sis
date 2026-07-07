<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Taalbeheersing van de student. Nederlands is voor de instelling belangrijk
 * (toelating/begeleiding); Arabisch is uitsluitend ter informatie. Daarnaast
 * of de student nog een NT2-examen moet afleggen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('studenten', function (Blueprint $table) {
            $table->string('taal_nederlands')->nullable()->after('diploma')
                ->comment('onvoldoende | voldoende | goed');
            $table->string('taal_arabisch')->nullable()->after('taal_nederlands')
                ->comment('onvoldoende | voldoende | goed (alleen ter info)');
            $table->boolean('nt2_examen_vereist')->default(false)->after('taal_arabisch')
                ->comment('student moet nog een NT2-examen afleggen');
        });
    }

    public function down(): void
    {
        Schema::table('studenten', function (Blueprint $table) {
            $table->dropColumn(['taal_nederlands', 'taal_arabisch', 'nt2_examen_vereist']);
        });
    }
};
