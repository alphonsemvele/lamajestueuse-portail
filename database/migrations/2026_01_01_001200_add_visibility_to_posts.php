<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Interrupteur d'affichage, independant de la date de publication.
     *
     * Jusqu'ici, masquer une publication obligeait a effacer sa date, donc a
     * perdre l'information. Les deux notions sont desormais separees :
     * `is_visible` dit si elle s'affiche, `published_at` dit depuis quand.
     *
     * Les publications existantes restent visibles.
     */
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->boolean('is_visible')->default(true)->after('is_featured');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('is_visible');
        });
    }
};
