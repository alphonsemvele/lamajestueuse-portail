<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module Personnel & paie du portail.
 *
 * Le groupe compte plusieurs employeurs (un par institut, avec son NIU et sa
 * CNPS), et un meme agent peut travailler dans plusieurs d'entre eux. C'est
 * donc le CONTRAT, et non la personne, qui porte la remuneration : une
 * identite, autant de contrats que d'employeurs, un bulletin par contrat.
 *
 * Le calcul reprend celui d'IUM : salaire de base de l'echelon, indemnites et
 * retenues du profil (fixes ou en pourcentage), puis ajustements du mois.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ------------------------------------------------------- employeurs
        Schema::create('employeurs', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('sigle', 20);
            // L'institut correspondant dans le portail, quand il en a un.
            $table->foreignId('application_id')->nullable()->constrained()->nullOnDelete();
            $table->string('niu', 40)->nullable();                 // identifiant fiscal
            $table->string('numero_cnps', 40)->nullable();
            $table->string('banque')->nullable();
            $table->string('compte_bancaire', 60)->nullable();
            $table->string('signataire')->nullable();              // qui signe les bulletins
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->unique('sigle');
        });

        // ------------------------------------------ dossier administratif
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->date('date_naissance')->nullable();
            $table->string('lieu_naissance')->nullable();
            $table->enum('situation_familiale', ['celibataire', 'marie', 'divorce', 'veuf'])->nullable();
            $table->unsignedTinyInteger('enfants')->default(0);
            $table->string('cni', 60)->nullable();
            $table->string('numero_cnps', 40)->nullable();
            $table->string('adresse')->nullable();
            $table->string('urgence_nom')->nullable();
            $table->string('urgence_telephone', 40)->nullable();
            $table->text('observations')->nullable();
            $table->timestamps();
        });

        Schema::create('diplomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->string('intitule');
            $table->string('niveau', 60)->nullable();              // BEPC, BAC, Licence…
            $table->string('specialite')->nullable();
            $table->string('etablissement')->nullable();
            $table->unsignedSmallInteger('annee_obtention')->nullable();
            $table->boolean('piece_fournie')->default(false);
            $table->timestamps();

            $table->index(['agent_id', 'annee_obtention']);
        });

        // ------------------------------------------------------ referentiels
        Schema::create('categories_rh', function (Blueprint $table) {
            $table->id();
            $table->string('libelle');
            $table->text('description')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('echelons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('categorie_rh_id')->constrained('categories_rh')->cascadeOnDelete();
            $table->unsignedTinyInteger('numero');
            $table->string('libelle')->nullable();
            $table->decimal('salaire', 12, 2)->default(0);
            $table->unsignedTinyInteger('anciennete_min')->default(0);   // annees
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->unique(['categorie_rh_id', 'numero']);
        });

        Schema::create('indemnites', function (Blueprint $table) {
            $table->id();
            $table->string('libelle');
            $table->text('description')->nullable();
            $table->boolean('imposable')->default(true);
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('retenues', function (Blueprint $table) {
            $table->id();
            $table->string('libelle');
            $table->text('description')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('profils_salaire', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->text('description')->nullable();
            $table->foreignId('categorie_rh_id')->nullable()->constrained('categories_rh')->nullOnDelete();
            $table->foreignId('echelon_id')->nullable()->constrained('echelons')->nullOnDelete();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('profil_indemnite', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profil_salaire_id')->constrained('profils_salaire')->cascadeOnDelete();
            $table->foreignId('indemnite_id')->constrained()->cascadeOnDelete();
            $table->enum('type_calcul', ['fixe', 'pourcentage'])->default('fixe');
            $table->decimal('valeur', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['profil_salaire_id', 'indemnite_id']);
        });

        Schema::create('profil_retenue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profil_salaire_id')->constrained('profils_salaire')->cascadeOnDelete();
            $table->foreignId('retenue_id')->constrained()->cascadeOnDelete();
            $table->enum('type_calcul', ['fixe', 'pourcentage'])->default('fixe');
            $table->decimal('valeur', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['profil_salaire_id', 'retenue_id']);
        });

        // ---------------------------------------------------------- carriere
        Schema::create('contrats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employeur_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['cdi', 'cdd', 'stage', 'vacation'])->default('cdi');
            $table->string('poste');
            $table->date('date_debut');
            $table->date('date_fin')->nullable();
            $table->unsignedTinyInteger('quotite')->default(100);   // % d'un temps plein
            $table->foreignId('profil_salaire_id')->nullable()->constrained('profils_salaire')->nullOnDelete();
            $table->foreignId('echelon_id')->nullable()->constrained('echelons')->nullOnDelete();
            $table->enum('statut', ['actif', 'suspendu', 'termine'])->default('actif');
            $table->string('motif_fin')->nullable();
            $table->text('observations')->nullable();
            $table->timestamps();

            $table->index(['employeur_id', 'statut']);
        });

        Schema::create('evenements_carriere', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contrat_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date_evenement');
            $table->enum('type', ['recrutement', 'avancement', 'affectation', 'formation', 'conge', 'sanction', 'depart']);
            $table->string('libelle');
            $table->text('details')->nullable();
            $table->foreignId('saisi_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['agent_id', 'date_evenement']);
        });

        // -------------------------------------------------------------- paie
        Schema::create('ajustements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrat_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('mois');
            $table->unsignedSmallInteger('annee');
            $table->enum('type', ['bonus', 'retenue']);
            $table->enum('mode', ['fixe', 'pourcentage'])->default('fixe');
            $table->string('libelle');
            $table->decimal('montant', 12, 2)->default(0);
            $table->text('motif')->nullable();
            $table->foreignId('saisi_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['contrat_id', 'annee', 'mois']);
        });

        Schema::create('bulletins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrat_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employeur_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('mois');
            $table->unsignedSmallInteger('annee');
            $table->decimal('salaire_base', 12, 2)->default(0);
            $table->decimal('total_indemnites', 12, 2)->default(0);
            $table->decimal('total_retenues', 12, 2)->default(0);
            $table->decimal('salaire_net', 12, 2)->default(0);
            // Le detail ligne a ligne, fige au moment du calcul : un bulletin
            // deja edite ne doit pas changer si le referentiel evolue ensuite.
            $table->json('detail')->nullable();
            $table->enum('statut', ['brouillon', 'valide', 'paye'])->default('brouillon');
            $table->foreignId('valide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('valide_le')->nullable();
            $table->foreignId('paye_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paye_le')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            // Un seul bulletin par contrat et par mois.
            $table->unique(['contrat_id', 'annee', 'mois']);
            $table->index(['employeur_id', 'annee', 'mois']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulletins');
        Schema::dropIfExists('ajustements');
        Schema::dropIfExists('evenements_carriere');
        Schema::dropIfExists('contrats');
        Schema::dropIfExists('profil_retenue');
        Schema::dropIfExists('profil_indemnite');
        Schema::dropIfExists('profils_salaire');
        Schema::dropIfExists('retenues');
        Schema::dropIfExists('indemnites');
        Schema::dropIfExists('echelons');
        Schema::dropIfExists('categories_rh');
        Schema::dropIfExists('diplomes');
        Schema::dropIfExists('agents');
        Schema::dropIfExists('employeurs');
    }
};
