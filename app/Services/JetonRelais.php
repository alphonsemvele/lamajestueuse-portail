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

        if (! is_string($emetteur) || $emetteur === '') {
            return null;
        }

        $relais = self::relais($emetteur);

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

    /**
     * Le jeton que le lecteur porte dans l'adresse ou il pousse ses
     * evenements.
     *
     * Il se deduit du secret partage plutot que d'etre tire au sort : les
     * deux cotes le calculent, personne ne le transporte, et il n'y a donc
     * qu'une seule valeur a installer — le secret.
     */
    public static function jetonPointage(?string $secret = null): ?string
    {
        $secret ??= self::secretDuRelais();

        return $secret === null ? null : substr(hash_hmac('sha256', 'pointage', $secret), 0, 48);
    }

    /** Le secret du relais : celui du .env s'il y est, celui de la base sinon. */
    public static function secretDuRelais(): ?string
    {
        $declare = (string) config('hikvision.portail.secret');

        if ($declare !== '') {
            return $declare;
        }

        $relais = RelaisLecteur::where('client_id', (string) config('hikvision.portail.client_id'))->first()
            ?? RelaisLecteur::first();

        return blank($relais?->client_secret) ? null : (string) $relais->client_secret;
    }

    /**
     * Le relais qui porte cet identifiant, et le secret qui va avec.
     *
     * Deux sources, dans cet ordre. Le .env du portail d'abord : c'est la
     * voie de celui qui n'a pas la main sur la base, et elle permet de poser
     * le meme secret des deux cotes sans rien lire nulle part. La base
     * ensuite, ou un secret a ete tire au sort a l'installation.
     */
    private static function relais(string $clientId): ?RelaisLecteur
    {
        $declare = (string) config('hikvision.portail.client_id');
        $secret = (string) config('hikvision.portail.secret');

        if ($clientId === $declare && $secret !== '') {
            $relais = RelaisLecteur::firstOrNew(['client_id' => $clientId], ['nom' => 'Relais du site']);
            $relais->client_secret = $secret;

            return $relais;
        }

        return RelaisLecteur::where('client_id', $clientId)->first();
    }
}
