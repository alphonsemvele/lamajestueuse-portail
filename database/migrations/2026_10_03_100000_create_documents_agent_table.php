<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pieces du dossier du personnel : CV, contrat signe, diplomes, et le reste.
 *
 * Les fichiers eux-memes vivent sur le disque prive : un bulletin, une copie
 * de CNI ou un contrat signe ne doivent pas etre atteignables par leur seule
 * adresse. Le telechargement passe par une route qui verifie le perimetre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents_agent', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30)->default('autre');
            $table->string('libelle');
            $table->string('fichier');
            $table->string('nom_origine');
            $table->string('type_mime', 120)->nullable();
            $table->unsignedInteger('taille')->default(0);
            $table->text('note')->nullable();
            $table->foreignId('depose_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['agent_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents_agent');
    }
};
