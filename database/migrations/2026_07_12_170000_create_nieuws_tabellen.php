<?php

use App\Enums\Nieuwsbrontype;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Onderwijsnieuws voor het bestuursdashboard. `nieuwsbronnen` = de (whitelisted)
 * bronnen; `nieuwsberichten` = de opgehaalde/handmatige items. Het dashboard leest
 * uitsluitend uit deze lokale tabellen — nooit live het internet op.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nieuwsbronnen', function (Blueprint $table) {
            $table->id();
            $table->string('naam');
            $table->string('url')->nullable();
            $table->enum('type', Nieuwsbrontype::waarden())->default('atom');
            $table->string('categorie')->nullable();
            // Scrape-configuratie (XPath), alleen voor type 'scrape'.
            $table->string('item_xpath')->nullable();
            $table->string('titel_xpath')->nullable();
            $table->string('link_xpath')->nullable();
            $table->string('datum_xpath')->nullable();
            $table->boolean('actief')->default(true);
            $table->unsignedSmallInteger('volgorde')->default(0);
            $table->timestamp('laatst_opgehaald_op')->nullable();
            $table->text('laatste_fout')->nullable();
            $table->timestamps();
        });

        Schema::create('nieuwsberichten', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nieuwsbron_id')->constrained('nieuwsbronnen')->cascadeOnDelete();
            $table->string('titel');
            $table->text('samenvatting')->nullable();
            $table->text('link');
            // Hash van de link als unieke sleutel (URLs kunnen te lang zijn voor een index).
            $table->char('link_hash', 64)->unique();
            $table->timestamp('gepubliceerd_op')->nullable();
            $table->timestamp('opgehaald_op')->nullable();
            $table->timestamps();

            $table->index(['nieuwsbron_id', 'gepubliceerd_op']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nieuwsberichten');
        Schema::dropIfExists('nieuwsbronnen');
    }
};
