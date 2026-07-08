<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extra profielvelden die via het publieke aanmeldportaal worden verzameld en
 * op de studentpagina thuishoren: huisnummer, provincie en de vooropleiding
 * (onderwijsinstelling + afstudeerjaar). Plus de markering dat documenten later
 * worden aangeleverd (voor de dashboardherinnering).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('studenten', function (Blueprint $table) {
            $table->string('huisnummer')->nullable()->after('adres');
            $table->string('provincie')->nullable()->after('woonplaats');
            $table->string('vorige_instelling')->nullable()->after('vooropleiding');
            $table->string('afstudeerjaar')->nullable()->after('vorige_instelling');
            $table->boolean('documenten_later')->default(false)->after('diploma');
        });
    }

    public function down(): void
    {
        Schema::table('studenten', function (Blueprint $table) {
            $table->dropColumn(['huisnummer', 'provincie', 'vorige_instelling', 'afstudeerjaar', 'documenten_later']);
        });
    }
};
