<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le journal gardait les entrees et les sorties ; il garde maintenant aussi
 * les pages visitees. La colonne dit laquelle — le nom de l'ecran, ou son
 * chemin quand il n'en porte pas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('access_logs', function (Blueprint $table) {
            $table->string('cible')->nullable()->after('action');
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('access_logs', function (Blueprint $table) {
            $table->dropIndex(['action', 'created_at']);
            $table->dropColumn('cible');
        });
    }
};
