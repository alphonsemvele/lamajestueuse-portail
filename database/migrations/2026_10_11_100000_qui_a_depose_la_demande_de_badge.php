<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qui a depose la demande de badge.
 *
 * Le guichet peut desormais en deposer une pour un employe — celui qui n'a
 * pas de compte, celui qui ne s'y retrouve pas, celui qu'on inscrit sur le
 * champ. Sans cette colonne, la demande paraitrait venir de l'interesse, et
 * personne ne saurait plus qui l'a faite ni pourquoi.
 *
 * Vide : la demande vient de son titulaire, comme c'est le cas ordinaire.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demandes_badge', function (Blueprint $table) {
            $table->unsignedBigInteger('depose_par')->nullable()->after('user_id');
            $table->index('depose_par');
        });
    }

    public function down(): void
    {
        Schema::table('demandes_badge', function (Blueprint $table) {
            $table->dropIndex(['depose_par']);
            $table->dropColumn('depose_par');
        });
    }
};
