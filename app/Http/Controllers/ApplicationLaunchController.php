<?php

namespace App\Http\Controllers;

use App\Models\AccessLog;
use App\Models\Application;
use App\Services\PortalIdentityToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Point de sortie unique du portail vers une application metier.
 *
 * Aujourd'hui : verification des droits, journalisation, puis redirection vers
 * l'URL declaree par l'administrateur.
 * Demain (phase SSO) : c'est ici que l'on construira l'URL /oauth/authorize
 * plutot que de rediriger directement.
 */
class ApplicationLaunchController extends Controller
{
    public function __invoke(Request $request, Application $application, PortalIdentityToken $jetons): SymfonyResponse
    {
        abort_unless($application->is_active, 404);

        $user = $request->user();

        // Le portail decide qui peut ouvrir quoi. Un employe sans acces ne
        // doit meme pas connaitre l'URL de l'application.
        abort_unless(
            $user->applications()->where('applications.id', $application->id)->exists(),
            403,
            "Vous n'avez pas accès à cette application."
        );

        /*
         * Application declaree mais pas encore en ligne : aucun lien a suivre.
         * On previent l'employe plutot que de le laisser sur une erreur, et on
         * ne compte pas cette tentative comme une ouverture.
         */
        if ($application->destination() === null) {
            return back()->with('status', __('« :app » sera bientôt disponible.', ['app' => $application->name]));
        }

        DB::table('application_user')
            ->where('user_id', $user->id)
            ->where('application_id', $application->id)
            ->update([
                'opens_count' => DB::raw('opens_count + 1'),
                'last_opened_at' => now(),
            ]);

        AccessLog::create([
            'user_id' => $user->id,
            'application_id' => $application->id,
            'action' => $application->usesPortalSignOn() ? 'sso' : 'open',
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
        ]);

        $destination = $application->destination();

        // Un module est servi par le portail : redirection interne ordinaire.
        if ($application->isModule()) {
            return redirect($destination);
        }

        /*
         * Application raccordee au portail : on ne l'ouvre pas sur sa page
         * d'accueil, on lui remet une identite signee. L'employe arrive
         * directement dans son espace, sans formulaire de connexion.
         */
        if ($application->usesPortalSignOn()) {
            $destination = $jetons->urlDeConnexion($user, $application);
        }

        /*
         * Les tuiles sont des liens Inertia : le clic part en XHR, et une XHR
         * ne peut pas suivre une redirection hors domaine. Inertia::location
         * repond 409 + X-Inertia-Location, ce que le client traduit en
         * navigation complete du navigateur. Pour une requete ordinaire, la
         * meme methode renvoie un 302 classique.
         */
        return Inertia::location($destination);
    }
}
