<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Centre d'aide du portail : questions frequentes et tutoriels illustres.
 *
 * La page est publique : on doit pouvoir la consulter avant d'avoir un compte,
 * depuis la page de connexion comme depuis le formulaire d'inscription.
 */
class SupportController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('support', [
            'contact' => config('support.contact'),
            'rubriques' => collect(config('support.rubriques', []))
                ->map(fn (array $rubrique) => [
                    'cle' => $rubrique['cle'],
                    'question' => $rubrique['question'],
                    'reponse' => $rubrique['reponse'] ?? null,
                    'points' => $rubrique['points'] ?? [],
                    'etapes' => collect($rubrique['etapes'] ?? [])
                        ->map(fn (array $etape) => [
                            'titre' => $etape['titre'],
                            'texte' => $etape['texte'],
                            // Une capture annoncee mais absente ne doit pas
                            // laisser une image cassee dans la page.
                            'capture' => $this->capture($etape['capture'] ?? null),
                        ])
                        ->all(),
                ])
                ->values()
                ->all(),
            // Le lien de retour depend de la provenance : connexion ou inscription.
            'retour' => str_contains((string) $request->headers->get('referer'), '/inscription')
                ? 'inscription'
                : 'connexion',
        ]);
    }

    private function capture(?string $chemin): ?string
    {
        if (blank($chemin) || ! file_exists(public_path($chemin))) {
            return null;
        }

        return asset($chemin);
    }
}
