<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * LA MAJESTUEUSE Info : la salle de presse du groupe.
 *
 * Une publication se range desormais dans une rubrique, porte autant
 * d'images et de documents qu'il faut, et peut sortir du portail : « visible
 * par tous » l'affiche sur le site public, sans compte. On y compte les
 * lectures et les partages.
 */
return new class extends Migration
{
    /** Les rubriques d'origine, celles que le portail connaissait deja. */
    private const RUBRIQUES = [
        ['nom' => 'Actualités', 'slug' => 'actualites', 'couleur' => '#7c3aed', 'famille' => 'news', 'ordre' => 1,
            'description' => 'La vie du groupe : évènements, nominations, réalisations.'],
        ['nom' => 'Annonces', 'slug' => 'annonces', 'couleur' => '#b45309', 'famille' => 'announcement', 'ordre' => 2,
            'description' => "Ce qu'il faut savoir maintenant : avis, rappels, consignes."],
        ['nom' => 'Affichage', 'slug' => 'affichage', 'couleur' => '#0f766e', 'famille' => 'billboard', 'ordre' => 3,
            'description' => 'Communiqués officiels et documents affichés.'],
    ];

    public function up(): void
    {
        /*
         * MyISAM ignore les cles etrangeres, et MySQL refuse de les poser sur
         * une table qui en releve (errno 150). La table « posts » de la
         * production vient d'un ancien export : on la ramene a InnoDB avant
         * de la rattacher a ses rubriques.
         */
        $this->passerEnInnoDb('posts');

        if (! Schema::hasTable('rubriques')) {
            $this->creerLesRubriques();
        }

        $this->completerLesPosts();

        if (! Schema::hasTable('post_medias')) {
            $this->creerLesMedias();
        }

        if (! Schema::hasTable('post_vues')) {
            $this->creerLesVues();
        }
    }

    // ------------------------------------------------------------- le detail

    private function creerLesRubriques(): void
    {
        Schema::create('rubriques', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 80);
            $table->string('slug', 90)->unique();
            $table->string('description', 220)->nullable();
            $table->string('couleur', 9)->default('#7c3aed');
            /*
             * La famille d'origine : le tableau de bord et l'administration
             * classent encore par « type ». Une rubrique la porte, et c'est
             * elle qui garnit posts.type a l'enregistrement.
             */
            $table->enum('famille', ['news', 'announcement', 'billboard'])->default('news');
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        $maintenant = now();

        DB::table('rubriques')->insert(array_map(
            fn (array $rubrique) => $rubrique + ['actif' => true, 'created_at' => $maintenant, 'updated_at' => $maintenant],
            self::RUBRIQUES,
        ));
    }

    /**
     * Les colonnes que « posts » gagne. Chacune est posee si elle manque :
     * une migration a moitie passee doit pouvoir reprendre.
     */
    private function completerLesPosts(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            if (! Schema::hasColumn('posts', 'rubrique_id')) {
                $table->unsignedBigInteger('rubrique_id')->nullable();
            }

            // Sortir du portail : l'article s'affiche sur le site public.
            if (! Schema::hasColumn('posts', 'is_public')) {
                $table->boolean('is_public')->default(true);
            }

            // Ce qui se mesure : les lectures sont deja comptees (posts.views).
            foreach (['partages_whatsapp', 'partages_facebook', 'partages_lien'] as $compteur) {
                if (! Schema::hasColumn('posts', $compteur)) {
                    $table->unsignedInteger($compteur)->default(0);
                }
            }

            // La legende de la photo de une, comme dans un journal.
            if (! Schema::hasColumn('posts', 'cover_legende')) {
                $table->string('cover_legende', 220)->nullable();
            }
        });

        if (! $this->aLIndex('posts', 'posts_is_public_published_at_index')) {
            Schema::table('posts', fn (Blueprint $table) => $table->index(['is_public', 'published_at']));
        }

        if (! $this->aLIndex('posts', 'posts_rubrique_id_index')) {
            Schema::table('posts', fn (Blueprint $table) => $table->index('rubrique_id'));
        }

        $this->cleEtrangere('posts', 'rubrique_id', 'rubriques', 'SET NULL');

        /*
         * Les publications deja en ligne restent internes : elles ont ete
         * ecrites pour le portail, parfois pour le seul personnel. C'est a
         * la redaction de decider, une par une, ce qui sort sur le site
         * public — pas a une migration.
         */
        DB::table('posts')->update(['is_public' => false]);

        // Chaque publication existante rejoint la rubrique de sa famille.
        foreach (self::RUBRIQUES as $rubrique) {
            DB::table('posts')
                ->where('type', $rubrique['famille'])
                ->update(['rubrique_id' => DB::table('rubriques')->where('slug', $rubrique['slug'])->value('id')]);
        }

    }

    private function creerLesMedias(): void
    {
        Schema::create('post_medias', function (Blueprint $table) {
            $table->id();
            // La contrainte est posee apres coup : elle depend du moteur de
            // « posts », et le menage se fait de toute facon dans le modele.
            $table->unsignedBigInteger('post_id');
            // Une image s'affiche dans l'article ; un document se telecharge.
            $table->enum('genre', ['image', 'document'])->default('image');
            $table->string('chemin', 255);
            $table->string('nom', 200);
            $table->unsignedBigInteger('taille')->default(0);
            $table->string('mime', 120)->nullable();
            $table->string('legende', 220)->nullable();
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();

            $table->index(['post_id', 'genre', 'ordre']);
        });

        $this->cleEtrangere('post_medias', 'post_id', 'posts', 'CASCADE');

    }

    /**
     * Une lecture par visiteur et par jour : sans cela, un rafraichissement
     * suffirait a gonfler le compteur, et le chiffre ne vaudrait rien.
     */
    private function creerLesVues(): void
    {
        Schema::create('post_vues', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('post_id');
            $table->char('empreinte', 64);
            $table->timestamp('created_at')->nullable();

            $table->unique(['post_id', 'empreinte'], 'vue_unicite');
        });

        $this->cleEtrangere('post_vues', 'post_id', 'posts', 'CASCADE');
    }

    /**
     * Ramene une table a InnoDB. MyISAM accepte la syntaxe des cles
     * etrangeres puis les oublie ; MySQL, lui, refuse de les poser depuis
     * une table InnoDB vers elle. Les deux comportements ont deja coute
     * cher dans ce groupe.
     */
    private function passerEnInnoDb(string $table): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $moteur = DB::selectOne(
            'SELECT ENGINE AS moteur FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table],
        )?->moteur;

        if ($moteur === null || strtoupper($moteur) === 'INNODB') {
            return;
        }

        try {
            DB::statement("ALTER TABLE `{$table}` ENGINE = InnoDB");
        } catch (Throwable $erreur) {
            Log::notice('LA MAJESTUEUSE Info : conversion InnoDB impossible.', [
                'table' => $table, 'raison' => $erreur->getMessage(),
            ]);
        }
    }

    /**
     * Pose une cle etrangere si la base l'accepte.
     *
     * Une base ancienne peut porter des tables MyISAM, qui ne savent pas
     * tenir de contrainte : selon le serveur, elle est ignoree ou refusee
     * (errno 150). L'integrite ne repose donc pas sur elle — le modele fait
     * le menage lui-meme — et un refus ne doit pas arreter la mise en ligne.
     */
    private function cleEtrangere(string $table, string $colonne, string $cible, string $surSuppression): void
    {
        if (DB::getDriverName() !== 'mysql' || $this->aLaContrainte($table, "{$table}_{$colonne}_foreign")) {
            return;
        }

        try {
            DB::statement(
                "ALTER TABLE `{$table}` ADD CONSTRAINT `{$table}_{$colonne}_foreign`
                 FOREIGN KEY (`{$colonne}`) REFERENCES `{$cible}` (`id`) ON DELETE {$surSuppression}"
            );
        } catch (Throwable $erreur) {
            Log::notice('LA MAJESTUEUSE Info : clé étrangère non posée, le ménage reste au modèle.', [
                'table' => $table, 'colonne' => $colonne, 'raison' => $erreur->getMessage(),
            ]);
        }
    }

    /**
     * La contrainte est-elle deja posee ?
     *
     * On la cherche parmi les contraintes, et non parmi les index : MySQL
     * reutilise un index existant pour une cle etrangere plutot que d'en
     * creer un du nom de la contrainte.
     */
    private function aLaContrainte(string $table, string $contrainte): bool
    {
        return DB::selectOne(
            'SELECT 1 AS present FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?',
            [$table, $contrainte],
        ) !== null;
    }

    /** La table porte-t-elle deja cet index ? */
    private function aLIndex(string $table, string $index): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return false;
        }

        return DB::selectOne(
            'SELECT 1 AS present FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$table, $index],
        ) !== null;
    }

    public function down(): void
    {
        Schema::dropIfExists('post_vues');
        Schema::dropIfExists('post_medias');

        if ($this->aLaContrainte('posts', 'posts_rubrique_id_foreign')) {
            DB::statement('ALTER TABLE `posts` DROP FOREIGN KEY `posts_rubrique_id_foreign`');
        }

        // Chaque retrait est garde : un aller-retour interrompu laisse la
        // table a moitie defaite, et la reprise ne doit pas trebucher.
        foreach (['posts_is_public_published_at_index', 'posts_rubrique_id_index'] as $index) {
            if ($this->aLIndex('posts', $index)) {
                Schema::table('posts', fn (Blueprint $table) => $table->dropIndex($index));
            }
        }

        $colonnes = array_values(array_filter(
            ['rubrique_id', 'is_public', 'partages_whatsapp', 'partages_facebook', 'partages_lien', 'cover_legende'],
            fn (string $colonne) => Schema::hasColumn('posts', $colonne),
        ));

        if ($colonnes !== []) {
            Schema::table('posts', fn (Blueprint $table) => $table->dropColumn($colonnes));
        }

        Schema::dropIfExists('rubriques');
    }
};
