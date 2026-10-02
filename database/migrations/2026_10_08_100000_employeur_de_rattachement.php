<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L'employeur de rattachement, choisi par le service du personnel.
 *
 * Rattache a un seul institut, l'employeur se deduit tout seul : la colonne
 * reste vide et rien n'est a saisir. Rattache a deux, la deduction serait
 * un coup de des : c'est la RH qui tranche, et son choix s'inscrit ici.
 *
 * Elle peut aussi l'inscrire pour quelqu'un qui n'a qu'un institut, afin de
 * le rattacher ailleurs. Le choix pose l'emporte toujours sur la deduction.
 *
 * Pas de cle etrangere : `users` et `employeurs` sont nees a des moments
 * differents de la vie du serveur, et MySQL refuse la contrainte entre deux
 * tables qui ne partagent pas le meme moteur. L'integrite tient cote
 * application — l'employeur efface libere ceux qu'il rattachait, et une
 * reference devenue orpheline rend simplement un rattachement vide.
 *
 * Le premier essai de cette migration a pose la colonne puis echoue sur la
 * contrainte : elle se verifie donc avant d'etre ajoutee.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'employeur_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('employeur_id')->nullable()->after('entite');
            $table->index('employeur_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'employeur_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['employeur_id']);
            $table->dropColumn('employeur_id');
        });
    }
};
