<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le cadrage des photos, a cote des photos.
 *
 * Recadrer ne doit pas entamer le fichier d'origine : on garde la photo
 * telle qu'elle a ete deposee, et l'on enregistre a cote la facon de la
 * regarder — le point qu'il faut montrer, et de combien l'agrandir. La meme
 * photo peut donc etre recadree autant de fois qu'on veut, et le jour ou le
 * cadrage ne convient plus, rien n'est perdu.
 *
 * Vide : la photo se montre centree, sans agrandissement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('avatar_cadrage')->nullable()->after('avatar');
        });

        Schema::table('demandes_badge', function (Blueprint $table) {
            $table->json('photo_cadrage')->nullable()->after('photo');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('avatar_cadrage');
        });

        Schema::table('demandes_badge', function (Blueprint $table) {
            $table->dropColumn('photo_cadrage');
        });
    }
};
