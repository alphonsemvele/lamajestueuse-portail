<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\EssaiEnvoi;
use App\Models\ReglageEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Reglages d'envoi des courriels, et essai depuis l'administration.
 *
 * Un envoi qui echoue le dit ici, avec le message du serveur : sans cet
 * ecran, une erreur de relais ne se decouvre qu'au moment ou un employe
 * n'a pas recu son mot de passe.
 */
class ReglageEmailController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/email/index', [
            'reglages' => ReglageEmail::actuels()->toUiArray(),
            'chiffrements' => ReglageEmail::CHIFFREMENTS,
            // Ce que le portail utilise a cet instant, reglages appliques.
            'active' => [
                'transport' => config('mail.default'),
                'hote' => config('mail.mailers.smtp.host'),
                'port' => config('mail.mailers.smtp.port'),
                'expediteur' => config('mail.from.address'),
                'nomExpediteur' => config('mail.from.name'),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $donnees = $request->validate([
            'actif' => ['boolean'],
            // L'hote n'est exige que si l'on active ces reglages : on peut
            // les preparer a vide, puis les activer.
            'hote' => [Rule::requiredIf($request->boolean('actif')), 'nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'identifiant' => ['nullable', 'string', 'max:255'],
            'mot_de_passe' => ['nullable', 'string', 'max:255'],
            'chiffrement' => ['nullable', Rule::in(array_keys(ReglageEmail::CHIFFREMENTS))],
            'expediteur' => ['nullable', 'email', 'max:255'],
            'nom_expediteur' => ['nullable', 'string', 'max:255'],
        ], [
            'hote.required' => __('Indiquez le serveur SMTP avant d’activer ces réglages.'),
        ]);

        $reglages = ReglageEmail::actuels();

        // Un champ mot de passe laisse vide garde celui qui est enregistre :
        // on ne le renvoie jamais a l'ecran, il ne peut donc pas revenir.
        if (blank($donnees['mot_de_passe'] ?? null)) {
            unset($donnees['mot_de_passe']);
        }

        $reglages->fill($donnees)->save();

        return back()->with('status', $reglages->actif
            ? __('Réglages enregistrés et appliqués.')
            : __('Réglages enregistrés. Le portail continue d’utiliser la configuration du serveur.'));
    }

    /** Envoie un message d'essai et rapporte ce que le serveur a repondu. */
    public function tester(Request $request): RedirectResponse
    {
        $donnees = $request->validate([
            'destinataire' => ['required', 'email', 'max:255'],
        ]);

        // Les reglages viennent d'etre enregistres : on les applique a cette
        // requete, sinon l'essai porterait sur l'ancienne configuration.
        ReglageEmail::appliquer();

        try {
            Mail::to($donnees['destinataire'])->send(new EssaiEnvoi($request->user()->fullName()));
        } catch (Throwable $e) {
            return back()->withErrors([
                'essai' => __('L’envoi a échoué : :erreur', ['erreur' => $this->messageLisible($e)]),
            ]);
        }

        ReglageEmail::actuels()->forceFill(['teste_le' => now()])->save();

        return back()->with('status', __('Message d’essai envoyé à :adresse.', [
            'adresse' => $donnees['destinataire'],
        ]));
    }

    /**
     * Le message du serveur, sans la trace ni les identifiants qu'elle peut
     * contenir.
     */
    private function messageLisible(Throwable $e): string
    {
        $message = trim(explode("\n", $e->getMessage())[0]);

        return mb_substr($message, 0, 300) ?: $e::class;
    }
}
