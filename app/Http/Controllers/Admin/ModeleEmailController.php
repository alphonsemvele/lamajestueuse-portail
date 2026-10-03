<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\CourrielDuPortail;
use App\Models\ReglageEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as Reponse;
use Throwable;

/**
 * Modeles de courriels : les relire et les essayer sans avoir a reproduire
 * la situation qui les declenche.
 *
 * Les donnees affichees sont fictives ; aucun envoi ne touche un destinataire
 * reel, et rien n'est enregistre.
 */
class ModeleEmailController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('admin/email/modeles', [
            'groupes' => collect(config('emails'))
                ->map(fn (array $courriels, string $groupe) => [
                    'nom' => $groupe,
                    'courriels' => collect($courriels)->map(fn (array $courriel, string $cle) => [
                        'cle' => $cle,
                        'titre' => $courriel['titre'],
                        'description' => $courriel['description'],
                    ])->values()->all(),
                ])->values()->all(),
            'destinataire' => $request->user()->email,
            'envoi' => [
                'transport' => config('mail.default'),
                'expediteur' => config('mail.from.address'),
            ],
        ]);
    }

    /** Le message tel qu'il arrivera, rendu dans le navigateur. */
    public function apercu(string $cle): Reponse
    {
        $courriel = $this->courriel($cle);

        return response($courriel::exemple()->render())
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }

    public function envoyer(Request $request, string $cle): RedirectResponse
    {
        $donnees = $request->validate([
            'destinataire' => ['required', 'email', 'max:255'],
        ]);

        $courriel = $this->courriel($cle);

        ReglageEmail::appliquer();

        try {
            Mail::to($donnees['destinataire'])->send($courriel::exemple());
        } catch (Throwable $e) {
            return back()->withErrors([
                'envoi' => __('L’envoi a échoué : :erreur', [
                    'erreur' => mb_substr(trim(explode("\n", $e->getMessage())[0]), 0, 300),
                ]),
            ]);
        }

        return back()->with('status', __('« :titre » envoyé à :adresse.', [
            'titre' => $this->catalogue()[$cle]['titre'],
            'adresse' => $donnees['destinataire'],
        ]));
    }

    /**
     * La classe du courriel demande.
     *
     * @return class-string<CourrielDuPortail>
     */
    private function courriel(string $cle): string
    {
        $courriel = $this->catalogue()[$cle] ?? abort(404);

        return $courriel['classe'];
    }

    /** Le catalogue a plat : la cle d'un courriel est unique tous groupes confondus. */
    private function catalogue(): array
    {
        return collect(config('emails'))->collapse()->all();
    }
}
