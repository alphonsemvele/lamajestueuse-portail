<?php

namespace App\Services;

use App\Models\RelaisLecteur;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * Le jeton que le relais du lecteur presente au portail.
 *
 * Meme protocole que les applications du groupe : une assertion courte,
 * signee avec le secret partage, bonne une seule fois. Le relais n'a donc ni
 * mot de passe ni session a tenir, et un jeton intercepte ne resservira pas.
 */
class JetonRelais
{
    public const ALGORITHME = 'HS256';

    /** Duree de vie : le temps d'un appel, pas davantage. */
    public const DUREE = 120;

    /** Signe une demande au nom d'un compte de relais. */
    public static function signer(string $clientId, string $secret, string $usage): string
    {
        $maintenant = time();

        return JWT::encode([
            'iss' => $clientId,
            'aud' => 'portail',
            'usage' => $usage,
            'jti' => (string) Str::uuid(),
            'iat' => $maintenant,
            'exp' => $maintenant + self::DUREE,
        ], $secret, self::ALGORITHME);
    }

    /**
     * Verifie un jeton et rend le relais qui l'a signe, ou null.
     *
     * L'emetteur est lu sans verification — juste de quoi choisir le secret —
     * puis la signature decide. Un jeton deja vu est refuse : il ne sert
     * qu'une fois.
     */
    public static function verifier(?string $jeton, string $usage): ?RelaisLecteur
    {
        $jeton = (string) $jeton;
        $parties = explode('.', $jeton);

        $emetteur = count($parties) === 3
            ? (json_decode((string) JWT::urlsafeB64Decode($parties[1]), true)['iss'] ?? null)
            : null;

        $relais = is_string($emetteur) && $emetteur !== ''
            ? RelaisLecteur::where('client_id', $emetteur)->first()
            : null;

        if ($relais === null || blank($relais->client_secret)) {
            return null;
        }

        try {
            $charge = JWT::decode($jeton, new Key($relais->client_secret, self::ALGORITHME));
        } catch (Throwable) {
            return null;
        }

        if (($charge->aud ?? null) !== 'portail' || ($charge->usage ?? null) !== $usage) {
            return null;
        }

        if (blank($charge->jti ?? null) || ! Cache::add('relais:'.$charge->jti, true, now()->addMinutes(5))) {
            return null;
        }

        return $relais;
    }
}
