<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('klassen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opleiding_id')->constrained('opleidingen')->cascadeOnDelete();
            $table->string('code')->comment('bv. IT-1');
            $table->string('naam')->nullable();
            $table->unsignedTinyInteger('leerjaar');
            $table->string('groep')->default('dag');
            $table->timestamps();

            $table->unique(['opleiding_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('klassen');
    }
};
