<?php

namespace App\Services;

use App\Models\Ajustement;
use App\Models\Bulletin;
use App\Models\Contrat;
use App\Models\Employeur;
use App\Models\ProfilSalaire;
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
            $resolues = $this->lignesDuProfil($profil, $base);
            $indemnites = $resolues['indemnites'];
            $retenues = $resolues['retenues'];
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
     * `$seulementLesManquants` ne cree que ce qui manque et laisse les
     * brouillons en place : de quoi ajouter un arrivant apres coup sans
     * defaire le travail deja fait sur le reste du mois.
     *
     * @return array{crees: int, recalcules: int, ignores: int, sans_remuneration: int}
     */
    public function genererMois(Employeur $employeur, int $mois, int $annee, bool $seulementLesManquants = false): array
    {
        $this->verifierPeriode($mois, $annee);

        $debut = sprintf('%04d-%02d-01', $annee, $mois);
        $fin = date('Y-m-t', strtotime($debut));

        $contrats = Contrat::with(['profil.indemnites', 'profil.retenues', 'echelon', 'agent'])
            ->where('employeur_id', $employeur->id)
            ->where('statut', 'actif')
            // Sans date de debut, le contrat est repute avoir toujours couru.
            ->where(fn ($q) => $q->whereNull('date_debut')->orWhereDate('date_debut', '<=', $fin))
            ->where(fn ($q) => $q->whereNull('date_fin')->orWhereDate('date_fin', '>=', $debut))
            ->get();

        $resultat = ['crees' => 0, 'recalcules' => 0, 'ignores' => 0, 'sans_remuneration' => 0];

        DB::transaction(function () use ($contrats, $employeur, $mois, $annee, $seulementLesManquants, &$resultat) {
            foreach ($contrats as $contrat) {
                $existant = Bulletin::where('contrat_id', $contrat->id)
                    ->where('mois', $mois)->where('annee', $annee)->first();

                if ($existant && ($seulementLesManquants || $existant->statut !== 'brouillon')) {
                    $resultat['ignores']++;

                    continue;
                }

                /*
                 * Un contrat sans echelon — ni le sien, ni celui de son
                 * profil — n'a pas de salaire de base : son bulletin ne
                 * porterait que des zeros. On ne le prepare pas, et on le
                 * signale pour que la RH complete le contrat.
                 */
                if ($contrat->echelonApplique() === null) {
                    $resultat['sans_remuneration']++;

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

    /**
     * Met le bulletin en paiement.
     *
     * Un brouillon se paie directement : c'est la meme personne qui arrete le
     * montant et qui ordonne le versement, l'etape de validation separee ne
     * protegeait de rien. Elle se joue au passage — le controle du net
     * negatif compris — et les deux horodatages se remplissent d'un coup, de
     * sorte que la trace reste complete.
     */
    public function payer(Bulletin $bulletin, ?int $par = null): Bulletin
    {
        if ($bulletin->statut === 'paye') {
            throw new RuntimeException('Ce bulletin est déjà payé.');
        }

        if ($bulletin->statut === 'brouillon') {
            $this->valider($bulletin, $par);
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
    /**
     * Ce qu'un profil verse a quotite pleine, hors ajustement du mois.
     *
     * Memes regles que calculer() — c'est la meme methode ligne() qui applique
     * les pourcentages — de sorte que le net lu sur un profil soit celui que
     * le bulletin produira.
     *
     * @return array{salaire_base: float, total_indemnites: float, total_retenues: float, salaire_net: float}
     */
    public function apercuProfil(ProfilSalaire $profil): array
    {
        $profil->loadMissing(['echelon', 'indemnites', 'retenues']);

        $base = (float) ($profil->echelon?->salaire ?? 0);

        $resolues = $this->lignesDuProfil($profil, $base);

        $somme = fn (array $lignes) => round(array_sum(array_column($lignes, 'montant')), 2);

        $indemnites = $somme($resolues['indemnites']);
        $retenues = $somme($resolues['retenues']);

        return [
            'salaire_base' => $base,
            'total_indemnites' => $indemnites,
            'total_retenues' => $retenues,
            'salaire_net' => round($base + $indemnites - $retenues, 2),
        ];
    }

    /**
     * Les lignes d'un profil, chacune calculee sur son assiette.
     *
     * Un pourcentage porte sur le salaire de base par defaut, mais peut
     * porter sur une autre ligne du meme profil — une retenue assise sur une
     * indemnite, par exemple. On resout donc par passes successives : a
     * chaque tour on calcule ce dont l'assiette est connue, jusqu'a ce que
     * plus rien n'avance.
     *
     * Une ligne qui renvoie dans le vide, ou prise dans un renvoi circulaire,
     * retombe sur le salaire de base plutot que de bloquer la paie : la
     * saisie l'interdit deja, ceci n'est qu'un filet.
     *
     * @return array{indemnites: list<array<string, mixed>>, retenues: list<array<string, mixed>>}
     */
    private function lignesDuProfil(ProfilSalaire $profil, float $base): array
    {
        $profil->loadMissing(['indemnites', 'retenues']);

        $brutes = [];

        foreach (['indemnite' => $profil->indemnites, 'retenue' => $profil->retenues] as $sens => $elements) {
            foreach ($elements as $element) {
                $brutes[$sens.':'.$element->id] = [
                    'sens' => $sens,
                    'libelle' => $element->libelle,
                    'type' => $element->pivot->type_calcul,
                    'valeur' => (float) $element->pivot->valeur,
                    'assiette' => $element->pivot->base_calcul ?: null,
                ];
            }
        }

        $resolues = [];

        // Au pire une ligne par passe : la chaine ne peut pas etre plus longue.
        for ($passe = 0; $passe <= count($brutes) && count($resolues) < count($brutes); $passe++) {
            foreach ($brutes as $cle => $ligne) {
                if (isset($resolues[$cle])) {
                    continue;
                }

                if ($ligne['assiette'] === null) {
                    $resolues[$cle] = $this->poser($ligne, $base, 'le salaire de base');

                    continue;
                }

                if (isset($resolues[$ligne['assiette']])) {
                    $appui = $resolues[$ligne['assiette']];
                    $resolues[$cle] = $this->poser($ligne, $appui['montant'], $appui['libelle']);
                }
            }
        }

        $indemnites = [];
        $retenues = [];

        foreach ($brutes as $cle => $ligne) {
            // Le filet : non resolue, la ligne retombe sur le salaire de base.
            $calculee = $resolues[$cle] ?? $this->poser($ligne, $base, 'le salaire de base');

            $ligne['sens'] === 'indemnite'
                ? $indemnites[] = $calculee
                : $retenues[] = $calculee;
        }

        return ['indemnites' => $indemnites, 'retenues' => $retenues];
    }

    /**
     * Pose une ligne sur son assiette. `assiette` et `assietteLibelle` restent
     * dans le detail fige, pour que le bulletin puisse dire sur quoi le
     * pourcentage a porte.
     *
     * @param  array<string, mixed>  $ligne
     * @return array<string, mixed>
     */
    private function poser(array $ligne, float $assiette, string $libelleAssiette): array
    {
        return $this->ligne($ligne['libelle'], $ligne['type'], $ligne['valeur'], $assiette, 'profil') + [
            'assiette' => round($assiette, 2),
            'assietteLibelle' => $libelleAssiette,
        ];
    }

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
