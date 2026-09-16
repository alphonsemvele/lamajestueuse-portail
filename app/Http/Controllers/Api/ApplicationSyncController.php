<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Services\ApplicationDirectorySync;
use App\Services\PortalIdentityToken;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Une application raccordée pousse son catalogue de rôles et son personnel
 * (`php artisan portail:synchroniser`). Le jeton est signé avec son secret
 * partagé et porte son client_id dans `iss`.
 */
class ApplicationSyncController extends Controller
{
    public function __invoke(Request $request, ApplicationDirectorySync $sync): JsonResponse
    {
        $jeton = (string) $request->bearerToken();

        // On lit l'emetteur sans verifier, uniquement pour choisir le secret.
        $parties = explode('.', $jeton);
        $emetteur = count($parties) === 3
            ? (json_decode((string) JWT::urlsafeB64Decode($parties[1]), true)['iss'] ?? null)
            : null;

        $application = is_string($emetteur) && $emetteur !== ''
            ? Application::where('client_id', $emetteur)->first()
            : null;

        if (! $application?->usesPortalSignOn()) {
            return response()->json(['message' => 'Application inconnue.'], 401);
        }

        try {
            $charge = JWT::decode($jeton, new Key($application->client_secret, PortalIdentityToken::ALGORITHME));
        } catch (Throwable $e) {
            Log::warning('Synchronisation : jeton rejeté.', ['application' => $application->slug, 'raison' => $e->getMessage()]);

            return response()->json(['message' => 'Jeton invalide ou expiré.'], 401);
        }

        if (($charge->aud ?? null) !== 'portail' || ($charge->usage ?? null) !== 'synchronisation') {
            return response()->json(['message' => 'Jeton destiné à un autre usage.'], 401);
        }

        if (blank($charge->jti ?? null) || ! Cache::add('synchronisation:'.$charge->jti, true, now()->addMinutes(5))) {
            return response()->json(['message' => 'Jeton déjà utilisé.'], 401);
        }

        return response()->json([
            'application' => $application->slug,
            'resultat' => $sync->apply($application, $request->json()->all()),
        ]);
    }
}
