<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extra rollen per gebruiker (multi-rol). `users.rol` blijft de PRIMAIRE rol —
 * die bepaalt het startdashboard, de standaard-scoping en de weergave. Deze
 * tabel houdt de aanvullende rollen; de rechten worden server-side als UNIE
 * over alle rollen bepaald (een gebruiker mag iets zodra één van zijn rollen
 * dat toestaat). De rolscheiding blijft daarmee expliciet en controleerbaar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roltoewijzingen', function (Blueprint $table) {
            $table->id(); // surrogaatsleutel
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Rolsleutel (Rol-enum). Bewust string i.p.v. enum-kolom: nieuwe rollen
            // vergen dan geen ALTER; de waarde wordt in de applicatie gevalideerd.
            $table->string('rol', 40);
            $table->timestamps();

            $table->unique(['user_id', 'rol']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roltoewijzingen');
    }
};
