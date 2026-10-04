<?php

namespace App\Services;

use App\Mail\Badge\BadgePret;
use App\Mail\Badge\BadgeRefuse;
use App\Mail\Badge\BadgeValide;
use App\Mail\Badge\DemandeEnregistree;
use App\Mail\Compte\CompteCree;
use App\Mail\Compte\CompteValide;
use App\Mail\Compte\DemandeRefusee;
use App\Mail\Compte\InscriptionRecue;
use App\Mail\CourrielDuPortail;
use App\Mail\Paie\BulletinDisponible;
use App\Models\Bulletin;
use App\Models\DemandeBadge;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Envoi des courriels du portail.
 *
 * Un relais injoignable ne doit jamais faire echouer la procedure elle-meme :
 * une inscription reste enregistree, un compte reste valide, meme si le
 * message ne part pas. L'echec est consigne dans le journal, et l'ecran des
 * reglages e-mail sert a le diagnostiquer.
 */
class CourrielsPortail
{
    /**
     * Pourquoi le dernier envoi n'est pas parti.
     *
     * L'echec ne doit pas arreter la procedure, mais celui qui valide un
     * compte doit l'apprendre sur le champ : sinon l'erreur ne vit que dans
     * le journal, l'ecran annonce une reussite, et l'employe attend un
     * message qui ne viendra jamais.
     */
    private ?string $dernierEchec = null;

    public function dernierEchec(): ?string
    {
        return $this->dernierEchec;
    }

    /**
     * Le message qui correspond a l'etat actuel d'un compte : c'est celui
     * qu'un renvoi doit reexpedier.
     */
    public function pourCompte(User $personne): ?CourrielDuPortail
    {
        $nom = $personne->fullName();

        return match (true) {
            $personne->status === 'pending' => new InscriptionRecue(
                $nom,
                $personne->applications()->pluck('name')->all(),
            ),
            $personne->status === 'suspended' => new DemandeRefusee($nom),
            // Un compte ouvert par le service du personnel n'a pas ete
            // demande par son titulaire : le message n'est pas le meme.
            ! $personne->self_registered => new CompteCree(
                $nom,
                $personne->email ?: $personne->matricule ?: '—',
                $personne->matricule,
                $personne->entite,
            ),
            default => new CompteValide(
                $nom,
                $personne->matricule,
                $personne->applications()->pluck('name')->all(),
            ),
        };
    }

    /** Le message qui correspond a l'etat actuel d'une demande de badge. */
    public function pourBadge(DemandeBadge $demande): ?CourrielDuPortail
    {
        $demande->loadMissing(['user', 'institut']);
        $nom = $demande->user?->fullName() ?? $demande->nom_affiche;

        return match ($demande->statut) {
            'imprimee', 'remise' => new BadgePret($nom, $demande->numero, $demande->logoLibelle()),
            'approuvee' => new BadgeValide($nom, $demande->numero, $demande->logoLibelle()),
            'refusee' => new BadgeRefuse($nom, $demande->numero, $demande->motif_refus),
            default => new DemandeEnregistree(
                $nom,
                $demande->numero,
                $demande->nom_affiche,
                $demande->logoLibelle(),
            ),
        };
    }

    /** Envoie a une personne, si elle a une adresse. */
    /**
     * L'annonce qui suit la mise en paiement. Sans titulaire ni periode
     * lisible, il n'y a rien a annoncer.
     */
    public function pourBulletin(Bulletin $bulletin): ?CourrielDuPortail
    {
        $bulletin->loadMissing(['agent.user', 'employeur']);

        $personne = $bulletin->agent?->user;

        if ($personne === null) {
            return null;
        }

        return new BulletinDisponible(
            $personne->fullName(),
            $bulletin->periode(),
            $bulletin->employeur?->nom,
        );
    }

    public function envoyerA(?User $destinataire, CourrielDuPortail $courriel): bool
    {
        $this->dernierEchec = null;

        if (! $destinataire) {
            $this->dernierEchec = __('Aucun destinataire : le message n’est pas parti.');

            return false;
        }

        // Depuis que l'adresse est facultative a l'inscription, un compte
        // peut n'en avoir aucune : il n'y a alors rien a envoyer, et c'est
        // la premiere chose a dire a l'administrateur.
        if (blank($destinataire->email)) {
            $this->dernierEchec = __('Aucun message envoyé : :nom n’a pas d’adresse e-mail. Renseignez-la dans sa fiche, puis renvoyez le message avec l’icône enveloppe.', [
                'nom' => $destinataire->fullName(),
            ]);

            return false;
        }

        return $this->envoyer($destinataire->email, $courriel);
    }

    public function envoyer(string $adresse, CourrielDuPortail $courriel): bool
    {
        $this->dernierEchec = null;

        try {
            Mail::to($adresse)->send($courriel);

            return true;
        } catch (Throwable $e) {
            Log::warning('Courriel non envoyé', [
                'courriel' => $courriel::class,
                'destinataire' => $adresse,
                'erreur' => $e->getMessage(),
            ]);

            $this->dernierEchec = __('L’envoi à :adresse a échoué : :erreur', [
                'adresse' => $adresse,
                'erreur' => $this->messageLisible($e),
            ]);

            return false;
        }
    }

    /**
     * Le message du serveur, sans la trace : elle peut porter les
     * identifiants du relais, qui n'ont rien a faire a l'ecran.
     */
    private function messageLisible(Throwable $e): string
    {
        $message = trim(explode("\n", $e->getMessage())[0]);

        return mb_substr($message, 0, 300) ?: $e::class;
    }
}
