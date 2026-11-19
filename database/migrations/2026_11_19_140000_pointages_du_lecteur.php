<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Les pointages, tels que le lecteur les annonce.
 *
 * L'appareil pousse chaque passage vers le portail des qu'il a lieu : une
 * carte presentee, un doigt reconnu, et la ligne arrive. On garde le brut a
 * cote du lu — un evenement mal interprete aujourd'hui se relit demain sans
 * avoir a retourner devant l'appareil.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pointages', function (Blueprint $table) {
            $table->id();

            // Le numero que porte la personne sur le lecteur : les chiffres
            // de son matricule. Le compte du portail suit quand il existe.
            $table->string('numero', 32)->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('nom')->nullable();

            $table->timestamp('moment')->index();
            $table->string('sens', 12)->default('inconnu');
            $table->string('moyen', 20)->nullable();
            $table->string('porte', 12)->nullable();

            // De quoi ne pas compter deux fois le meme passage : l'appareil
            // repete ses annonces quand il doute d'avoir ete entendu.
            $table->string('empreinte', 64)->unique();
            $table->json('brut')->nullable();
            $table->timestamps();

            $table->index(['moment', 'numero']);
        });

        Schema::table('relais_lecteur', function (Blueprint $table) {
            // Le jeton que le lecteur porte dans l'adresse ou il pousse ses
            // evenements : son URL n'accepte ni en-tete ni signature.
            $table->string('jeton_pointage', 64)->nullable()->after('client_secret');
        });

        DB::table('relais_lecteur')->update(['jeton_pointage' => Str::random(48)]);
    }

    public function down(): void
    {
        Schema::dropIfExists('pointages');

        Schema::table('relais_lecteur', function (Blueprint $table) {
            $table->dropColumn('jeton_pointage');
        });
    }
};
