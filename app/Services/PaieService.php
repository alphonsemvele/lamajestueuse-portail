<?php

namespace App\Services;

use App\Models\Ajustement;
use App\Models\Bulletin;
use App\Models\Contrat;
use App\Models\Employeur;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Calcul de la paie, repris de ce qui tourne a l'IUM :
 *
 *   salaire de base  = salaire de l'echelon, proratise par la quotite
 *   + indemnites     = celles du profil, en montant fixe ou en pourcentage du base
 *   - retenues       = celles du profil, meme principe
 *   +/- ajustements  = primes et retenues exceptionnelles du mois
 *   = salaire net
 *
 * Chaque bulletin conserve son detail ligne a ligne : modifier un profil plus
 * tard ne change pas un bulletin deja edite.
 */
class PaieService
{
    /**
     * Detaille la paie d'un contrat pour un mois donne, sans rien enregistrer.
     *
     * @return array{salaire_base: float, total_indemnites: float, total_retenues: float, salaire_net: float, detail: array}
     */
    public function calculer(Contrat $contrat, int $mois, int $annee): array
    {
        $base = $contrat->salaireBase();
        $indemnites = [];
        $retenues = [];

        $profil = $contrat->profil;

        if ($profil) {
            $profil->loadMissing(['indemnites', 'retenues']);

            foreach ($profil->indemnites as $indemnite) {
                $indemnites[] = $this->ligne(
                    $indemnite->libelle,
                    $indemnite->pivot->type_calcul,
                    (float) $indemnite->pivot->valeur,
                    $base,
                    'profil'
                );
            }

            foreach ($profil->retenues as $retenue) {
                $retenues[] = $this->ligne(
                    $retenue->libelle,
                    $retenue->pivot->type_calcul,
                    (float) $retenue->pivot->valeur,
                    $base,
                    'profil'
                );
            }
        }

        $ajustements = Ajustement::where('contrat_id', $contrat->id)
            ->where('mois', $mois)->where('annee', $annee)->get();

        foreach ($ajustements as $ajustement) {
            $ligne = $this->ligne(
                $ajustement->libelle,
                $ajustement->mode,
                (float) $ajustement->montant,
                $base,
                'ajustement'
            );

            if ($ajustement->type === 'bonus') {
                $indemnites[] = $ligne;
            } else {
                $retenues[] = $ligne;
            }
        }

        $totalIndemnites = round(array_sum(array_column($indemnites, 'montant')), 2);
        $totalRetenues = round(array_sum(array_column($retenues, 'montant')), 2);

        return [
            'salaire_base' => $base,
            'total_indemnites' => $totalIndemnites,
            'total_retenues' => $totalRetenues,
            'salaire_net' => round($base + $totalIndemnites - $totalRetenues, 2),
            'detail' => ['indemnites' => $indemnites, 'retenues' => $retenues],
        ];
    }

    /**
     * Prepare les bulletins du mois pour tous les contrats actifs d'un employeur.
     *
     * Un bulletin deja valide ou paye n'est jamais touche ; un brouillon est
     * recalcule, pour tenir compte d'un ajustement saisi entre-temps.
     *
     * @return array{crees: int, recalcules: int, ignores: int}
     */
    public function genererMois(Employeur $employeur, int $mois, int $annee): array
    {
        $this->verifierPeriode($mois, $annee);

        $debut = sprintf('%04d-%02d-01', $annee, $mois);
        $fin = date('Y-m-t', strtotime($debut));

        $contrats = Contrat::with(['profil.indemnites', 'profil.retenues', 'echelon', 'agent'])
            ->where('employeur_id', $employeur->id)
            ->where('statut', 'actif')
            ->whereDate('date_debut', '<=', $fin)
            ->where(fn ($q) => $q->whereNull('date_fin')->orWhereDate('date_fin', '>=', $debut))
            ->get();

        $resultat = ['crees' => 0, 'recalcules' => 0, 'ignores' => 0];

        DB::transaction(function () use ($contrats, $employeur, $mois, $annee, &$resultat) {
            foreach ($contrats as $contrat) {
                $existant = Bulletin::where('contrat_id', $contrat->id)
                    ->where('mois', $mois)->where('annee', $annee)->first();

                if ($existant && $existant->statut !== 'brouillon') {
                    $resultat['ignores']++;

                    continue;
                }

                $calcul = $this->calculer($contrat, $mois, $annee);

                if ($existant) {
                    $existant->update($calcul);
                    $resultat['recalcules']++;

                    continue;
                }

                Bulletin::create($calcul + [
                    'contrat_id' => $contrat->id,
                    'employeur_id' => $employeur->id,
                    'agent_id' => $contrat->agent_id,
                    'mois' => $mois,
                    'annee' => $annee,
                    'statut' => 'brouillon',
                ]);
                $resultat['crees']++;
            }
        });

        return $resultat;
    }

    /** Recalcule un brouillon apres un changement de profil ou d'ajustement. */
    public function recalculer(Bulletin $bulletin): Bulletin
    {
        if ($bulletin->statut !== 'brouillon') {
            throw new RuntimeException('Ce bulletin est '.($bulletin->statut === 'paye' ? 'payé' : 'validé').' : il ne peut plus être recalculé.');
        }

        $bulletin->load('contrat.profil.indemnites', 'contrat.profil.retenues', 'contrat.echelon');
        $bulletin->update($this->calculer($bulletin->contrat, $bulletin->mois, $bulletin->annee));

        return $bulletin->refresh();
    }

    public function valider(Bulletin $bulletin, ?int $par = null): Bulletin
    {
        if ($bulletin->statut !== 'brouillon') {
            throw new RuntimeException('Seul un brouillon peut être validé.');
        }

        if ((float) $bulletin->salaire_net < 0) {
            throw new RuntimeException('Le net est négatif : corrigez les retenues avant de valider.');
        }

        $bulletin->update(['statut' => 'valide', 'valide_par' => $par, 'valide_le' => now()]);

        return $bulletin->refresh();
    }

    public function payer(Bulletin $bulletin, ?int $par = null): Bulletin
    {
        if ($bulletin->statut !== 'valide') {
            throw new RuntimeException('Un bulletin doit être validé avant d’être payé.');
        }

        $bulletin->update(['statut' => 'paye', 'paye_par' => $par, 'paye_le' => now()]);

        return $bulletin->refresh();
    }

    /**
     * Masse salariale d'un mois, par employeur.
     *
     * `$perimetre` limite le total aux entites qu'un gestionnaire suit ; null
     * signifie « tout le groupe ».
     *
     * @return array{brut: float, retenues: float, net: float, bulletins: int, a_payer: float}
     */
    public function masseSalariale(int $mois, int $annee, ?int $employeurId = null, ?array $perimetre = null): array
    {
        $bulletins = Bulletin::where('mois', $mois)->where('annee', $annee)
            ->when($employeurId, fn ($q) => $q->where('employeur_id', $employeurId))
            ->when($perimetre !== null, fn ($q) => $q->whereIn('employeur_id', $perimetre))
            ->get();

        return [
            'bulletins' => $bulletins->count(),
            'brut' => (float) $bulletins->sum(fn ($b) => (float) $b->salaire_base + (float) $b->total_indemnites),
            'retenues' => (float) $bulletins->sum('total_retenues'),
            'net' => (float) $bulletins->sum('salaire_net'),
            'a_payer' => (float) $bulletins->where('statut', '!=', 'paye')->sum('salaire_net'),
        ];
    }

    /**
     * Une ligne de bulletin : on garde la regle appliquee, pas seulement le montant.
     *
     * @return array{libelle: string, type: string, valeur: float, montant: float, source: string}
     */
    private function ligne(string $libelle, string $type, float $valeur, float $base, string $source): array
    {
        $montant = $type === 'pourcentage' ? round($base * $valeur / 100, 2) : round($valeur, 2);

        return [
            'libelle' => $libelle,
            'type' => $type,
            'valeur' => $valeur,
            'montant' => $montant,
            'source' => $source,
        ];
    }

    private function verifierPeriode(int $mois, int $annee): void
    {
        if ($mois < 1 || $mois > 12) {
            throw new RuntimeException('Mois invalide.');
        }

        if ($annee < 2000 || $annee > (int) date('Y') + 1) {
            throw new RuntimeException('Année invalide.');
        }
    }
}
