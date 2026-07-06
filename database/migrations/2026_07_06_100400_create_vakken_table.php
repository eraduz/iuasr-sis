<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vakken', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opleiding_id')->constrained('opleidingen')->cascadeOnDelete();
            $table->foreignId('docent_id')->nullable()->constrained('docenten')->nullOnDelete();
            $table->string('code')->unique();
            $table->string('naam');
            $table->unsignedSmallInteger('ec');
            $table->unsignedTinyInteger('leerjaar')->nullable();
            $table->unsignedTinyInteger('blok')->nullable();
            $table->boolean('actief')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vakken');
    }
};
