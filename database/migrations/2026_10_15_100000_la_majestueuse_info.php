<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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

        Schema::table('posts', function (Blueprint $table) {
            $table->foreignId('rubrique_id')->nullable()->constrained('rubriques')->nullOnDelete();

            // Sortir du portail : l'article s'affiche sur le site public.
            $table->boolean('is_public')->default(true);

            // Ce qui se mesure : les lectures sont deja comptees (posts.views).
            $table->unsignedInteger('partages_whatsapp')->default(0);
            $table->unsignedInteger('partages_facebook')->default(0);
            $table->unsignedInteger('partages_lien')->default(0);

            // La legende de la photo de une, comme dans un journal.
            $table->string('cover_legende', 220)->nullable();

            $table->index(['is_public', 'published_at']);
        });

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

        Schema::create('post_medias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
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

        /*
         * Une lecture par visiteur et par jour : sans cela, un rafraichissement
         * suffirait a gonfler le compteur, et le chiffre ne vaudrait rien.
         */
        Schema::create('post_vues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->char('empreinte', 64);
            $table->timestamp('created_at')->nullable();

            $table->unique(['post_id', 'empreinte'], 'vue_unicite');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_vues');
        Schema::dropIfExists('post_medias');

        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex(['is_public', 'published_at']);
            $table->dropConstrainedForeignId('rubrique_id');
            $table->dropColumn([
                'is_public', 'partages_whatsapp', 'partages_facebook', 'partages_lien', 'cover_legende',
            ]);
        });

        Schema::dropIfExists('rubriques');
    }
};
