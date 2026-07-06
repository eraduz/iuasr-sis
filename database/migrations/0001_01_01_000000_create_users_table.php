<?php

use App\Enums\Rol;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Medewerker-accounts. Authenticatie via Entra ID (SSO/OIDC); geen eigen
 * wachtwoordbeheer. De rol bepaalt server-side de rechten (rolscheiding).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id(); // surrogaatsleutel
            $table->string('naam');
            $table->string('email')->unique();
            $table->string('entra_oid')->nullable()->unique()->comment('Entra ID object-id (SSO)');
            $table->enum('rol', Rol::waarden())->index();
            // Koppeling naar docentprofiel (voor rol Docent — eigen vak).
            // FK wordt toegevoegd nadat de tabel docenten bestaat.
            $table->foreignId('docent_id')->nullable();
            $table->boolean('actief')->default(true);
            $table->timestamp('laatst_ingelogd_op')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
