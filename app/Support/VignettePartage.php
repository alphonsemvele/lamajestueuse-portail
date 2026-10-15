<?php

namespace App\Support;

use App\Models\Post;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * La vignette qu'un reseau affiche quand on partage un article.
 *
 * WhatsApp ne telecharge qu'une image legere — au-dela d'environ 300 Ko il
 * renonce et n'affiche aucun apercu, la ou Facebook, lui, accepte plusieurs
 * megaoctets. Une affiche de 1600 x 1600 pesant 650 Ko passait donc sur l'un
 * et pas sur l'autre. On fabrique ici une copie au format attendu, une fois,
 * et on la garde a cote de l'originale, qui n'est pas touchee.
 */
class VignettePartage
{
    /** Le format que reclament Facebook et WhatsApp pour une grande vignette. */
    public const LARGEUR = 1200;

    public const HAUTEUR = 630;

    /** Dossier des copies, sur le disque public. */
    private const DOSSIER = 'publications/partage';

    /**
     * L'adresse de la vignette d'un article, fabriquee au besoin.
     *
     * Rend l'image d'origine si elle n'est pas chez nous (URL externe) ou si
     * la fabrication echoue : mieux vaut une vignette imparfaite qu'aucune.
     */
    public static function pour(Post $post, ?string $defaut = null): ?string
    {
        $source = $post->image;

        if (blank($source)) {
            return $defaut;
        }

        // Une URL externe ou un visuel livre avec le projet : on ne le touche pas.
        if (str_starts_with($source, 'http://') || str_starts_with($source, 'https://') || str_starts_with($source, 'images/')) {
            return $post->imageUrl();
        }

        $disque = Storage::disk('public');

        if (! $disque->exists($source)) {
            return $defaut;
        }

        /*
         * Le nom porte la date du fichier : remplacer l'image de une refait
         * la vignette, et le lien partage change avec elle.
         */
        $nom = self::DOSSIER.'/'.substr(sha1($source.'|'.$disque->lastModified($source)), 0, 20).'.jpg';

        if ($disque->exists($nom)) {
            return $disque->url($nom);
        }

        try {
            $disque->put($nom, self::fabriquer($disque->path($source)));
        } catch (Throwable $erreur) {
            Log::notice('Vignette de partage : fabrication impossible.', [
                'image' => $source, 'raison' => $erreur->getMessage(),
            ]);

            return $post->imageUrl();
        }

        return $disque->url($nom);
    }

    /**
     * Met l'image au format de la vignette.
     *
     * Une photo large remplit le cadre : on la recadre au centre, la perte
     * est marginale. Une affiche carree ou verticale, elle, perdrait
     * l'essentiel — titre en haut, coordonnees en bas : on la pose entiere
     * sur un fond clair, quitte a laisser deux bandes sur les cotes.
     */
    private static function fabriquer(string $chemin): string
    {
        $source = @imagecreatefromstring((string) file_get_contents($chemin));

        if ($source === false) {
            throw new \RuntimeException("Image illisible : {$chemin}");
        }

        $largeur = imagesx($source);
        $hauteur = imagesy($source);
        $format = $largeur / max($hauteur, 1);

        $vignette = imagecreatetruecolor(self::LARGEUR, self::HAUTEUR);
        imagefill($vignette, 0, 0, imagecolorallocate($vignette, 255, 255, 255));

        // Au-dela de ces proportions, recadrer reviendrait a amputer.
        $remplir = $format >= 1.35 && $format <= 2.5;

        if ($remplir) {
            $facteur = max(self::LARGEUR / $largeur, self::HAUTEUR / $hauteur);
            $prise = [(int) round(self::LARGEUR / $facteur), (int) round(self::HAUTEUR / $facteur)];

            imagecopyresampled(
                $vignette, $source,
                0, 0,
                (int) round(($largeur - $prise[0]) / 2), (int) round(($hauteur - $prise[1]) / 2),
                self::LARGEUR, self::HAUTEUR, $prise[0], $prise[1],
            );
        } else {
            $facteur = min(self::LARGEUR / $largeur, self::HAUTEUR / $hauteur);
            $posee = [(int) round($largeur * $facteur), (int) round($hauteur * $facteur)];

            imagecopyresampled(
                $vignette, $source,
                (int) round((self::LARGEUR - $posee[0]) / 2), (int) round((self::HAUTEUR - $posee[1]) / 2),
                0, 0,
                $posee[0], $posee[1], $largeur, $hauteur,
            );
        }

        imagedestroy($source);

        // On descend la qualite tant que le fichier reste trop lourd pour
        // WhatsApp, sans jamais sacrifier la lisibilite.
        foreach ([82, 70, 60, 50] as $qualite) {
            ob_start();
            imagejpeg($vignette, null, $qualite);
            $donnees = (string) ob_get_clean();

            if (strlen($donnees) <= 280 * 1024) {
                break;
            }
        }

        imagedestroy($vignette);

        return $donnees;
    }
}
