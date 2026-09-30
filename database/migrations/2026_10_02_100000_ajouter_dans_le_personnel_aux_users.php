<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Distingue le personnel du groupe des comptes qui n'en font pas partie.
 *
 * Un administrateur technique, un compte de service ou un prestataire ont
 * besoin d'entrer dans le portail sans figurer pour autant dans les dossiers
 * du personnel, la paie ou les effectifs.
 *
 * Par defaut tout le monde en fait partie : c'est le cas courant, et les
 * comptes existants ne changent pas de nature.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('dans_le_personnel')->default(true)->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('dans_le_personnel');
        });
    }
};
