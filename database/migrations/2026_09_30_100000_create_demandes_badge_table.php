<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Demandes de badge du personnel.
 *
 * L'employe choisit le nom qui figurera sur son badge et le modele ; s'il
 * sert plusieurs instituts, il designe celui dont le logo sera imprime. Le
 * badge appartient donc a une personne et a un institut, pas a un contrat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demandes_badge', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 20)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // L'institut dont le logo figure sur le badge.
            $table->foreignId('application_id')->nullable()->constrained()->nullOnDelete();

            $table->string('nom_affiche', 80);
            $table->string('poste_affiche', 120)->nullable();
            $table->string('modele', 30)->default('classique');
            $table->enum('motif', ['premiere', 'renouvellement', 'perte', 'changement'])->default('premiere');

            // Photo propre au badge : a defaut, celle du compte du portail.
            $table->string('photo')->nullable();
            $table->text('commentaire')->nullable();

            $table->enum('statut', ['en_attente', 'approuvee', 'imprimee', 'remise', 'refusee'])
                ->default('en_attente');
            $table->text('motif_refus')->nullable();
            $table->foreignId('traite_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('traite_le')->nullable();

            $table->timestamps();

            $table->index(['statut', 'created_at']);
            $table->index(['user_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demandes_badge');
    }
};
