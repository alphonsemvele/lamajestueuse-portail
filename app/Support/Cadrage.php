<?php

namespace App\Support;

/**
 * Le cadrage d'une photo : ou regarder, et de combien agrandir.
 *
 * Trois nombres suffisent. `x` et `y` donnent en pourcentage le point de la
 * photo qui doit se retrouver au centre du cadre ; `zoom` dit de combien
 * l'agrandir. Rien n'est applique au fichier : il reste tel qu'il a ete
 * depose, et le cadrage se refait autant de fois qu'on veut.
 *
 * Ces valeurs viennent du navigateur : elles sont bornees ici, une fois,
 * plutot que dans chaque formulaire qui les recoit.
 */
class Cadrage
{
    /** Agrandissement maximal : au-dela, la photo se delite a l'impression. */
    public const ZOOM_MAX = 3.0;

    /**
     * Lit un cadrage envoye par un formulaire : un objet JSON, ou rien.
     *
     * @return array{x: float, y: float, zoom: float}|null
     */
    public static function depuis(mixed $valeur): ?array
    {
        if (is_string($valeur)) {
            $valeur = json_decode($valeur, true);
        }

        if (! is_array($valeur)) {
            return null;
        }

        $cadrage = [
            'x' => self::borne($valeur['x'] ?? null, 0, 100, 50),
            'y' => self::borne($valeur['y'] ?? null, 0, 100, 50),
            'zoom' => self::borne($valeur['zoom'] ?? null, 1, self::ZOOM_MAX, 1),
        ];

        // Un cadrage neutre ne vaut pas la peine d'etre enregistre : c'est
        // deja ce que fait l'affichage sans rien.
        return $cadrage === ['x' => 50.0, 'y' => 50.0, 'zoom' => 1.0] ? null : $cadrage;
    }

    private static function borne(mixed $valeur, float $min, float $max, float $defaut): float
    {
        if (! is_numeric($valeur)) {
            return $defaut;
        }

        return round(max($min, min($max, (float) $valeur)), 2);
    }
}
