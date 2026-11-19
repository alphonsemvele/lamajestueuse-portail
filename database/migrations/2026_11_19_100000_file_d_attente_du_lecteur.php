<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * La file d'attente du lecteur de presence, et l'identite de son relais.
 *
 * L'adresse du lecteur est privee : le portail, qui vit chez l'hebergeur, ne
 * l'atteindra jamais. Le portail depose donc ses ordres ici, et un relais
 * installe sur le site vient les chercher, les execute devant l'appareil et
 * rapporte ce qu'il a fait. Rien n'entre sur le reseau de l'etablissement :
 * c'est le relais qui sort.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taches_lecteur', function (Blueprint $table) {
            $table->id();
            $table->string('action', 40);
            $table->json('parametres')->nullable();
            $table->string('statut', 20)->default('en_attente');
            $table->json('resultat')->nullable();
            $table->text('erreur')->nullable();
            $table->foreignId('demande_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('prise_le')->nullable();
            $table->timestamp('terminee_le')->nullable();
            $table->timestamps();

            $table->index(['statut', 'id']);
        });

        /*
         * Le relais n'est pas une application du portail : il n'a pas d'ecran,
         * personne ne l'ouvre, et il n'a rien a faire dans la grille des
         * tuiles. Il tient donc son identite a part — un nom, un secret.
         */
        Schema::create('relais_lecteur', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('client_id')->unique();
            $table->text('client_secret');
            $table->timestamp('derniere_visite')->nullable();
            $table->timestamps();
        });

        DB::table('relais_lecteur')->insert([
            'nom' => 'Relais du site',
            'client_id' => 'relais-lecteur',
            'client_secret' => encrypt(Str::random(64)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('taches_lecteur');
        Schema::dropIfExists('relais_lecteur');
    }
};
