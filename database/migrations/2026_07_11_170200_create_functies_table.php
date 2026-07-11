<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Functies (opzoektabel, module HR): docent, staf, management, e.d. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('functies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('naam');
            $table->string('categorie', 20)->default('staf'); // docent | staf | management
            $table->boolean('actief')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('functies');
    }
};
