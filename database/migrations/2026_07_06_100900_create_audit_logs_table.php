<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit-log — onwijzigbaar (append-only) spoor van inzage en mutatie van
 * gevoelige gegevens (cijfers en BSN): wie, wat, wanneer, welk record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rol')->nullable();
            $table->string('actie')->comment('inzage | aanmaak | wijziging | verwijdering');
            $table->string('onderwerp_type')->comment('bv. Resultaat, Student');
            $table->unsignedBigInteger('onderwerp_id')->nullable();
            $table->string('veld')->nullable()->comment('bv. bsn, cijfer');
            $table->string('ip_adres', 45)->nullable();
            $table->json('context')->nullable();
            $table->timestamp('gelogd_op')->useCurrent();

            $table->index(['onderwerp_type', 'onderwerp_id']);
            $table->index(['actie', 'gelogd_op']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
