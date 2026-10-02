<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les pieces soumises par l'agent attendent la validation du service RH.
 *
 * Jusqu'ici, diplomes et documents n'entraient au dossier que par la main
 * de la RH : ils etaient donc vrais par construction. Maintenant que
 * chacun peut deposer les siens depuis « Mon profil », il faut distinguer
 * ce qui est verifie de ce qui attend de l'etre.
 *
 * Tout ce qui existe deja a ete saisi par la RH : c'est donc valide.
 */
return new class extends Migration
{
    private const TABLES = ['diplomes', 'documents_agent'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('statut', 20)->default('valide')->after('agent_id');
                // Qui l'a depose, quand c'est l'interesse lui-meme.
                $t->foreignId('soumis_par')->nullable()->after('statut');
                $t->foreignId('decide_par')->nullable()->after('soumis_par');
                $t->timestamp('decide_le')->nullable()->after('decide_par');
                $t->string('motif_refus')->nullable()->after('decide_le');

                $t->index(['agent_id', 'statut']);
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropIndex(['agent_id', 'statut']);
                $t->dropColumn(['statut', 'soumis_par', 'decide_par', 'decide_le', 'motif_refus']);
            });
        }
    }
};
