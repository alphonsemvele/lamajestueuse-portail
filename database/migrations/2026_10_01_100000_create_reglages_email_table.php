<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reglages d'envoi des courriels, modifiables depuis l'administration.
 *
 * Une seule ligne : tant qu'elle est inactive, le portail s'en tient a la
 * configuration du serveur (le .env). Le mot de passe est chiffre en base.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reglages_email', function (Blueprint $table) {
            $table->id();
            $table->boolean('actif')->default(false);
            $table->string('hote')->nullable();
            $table->unsignedSmallInteger('port')->nullable();
            $table->string('identifiant')->nullable();
            $table->text('mot_de_passe')->nullable();
            $table->string('chiffrement', 10)->nullable();
            $table->string('expediteur')->nullable();
            $table->string('nom_expediteur')->nullable();
            $table->timestamp('teste_le')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reglages_email');
    }
};
