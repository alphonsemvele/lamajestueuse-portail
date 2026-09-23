<?php

namespace App\Http\Controllers\Modules\Personnel;

use App\Http\Controllers\Concerns\ServesModule;
use App\Http\Controllers\Controller;
use App\Models\Ajustement;
use App\Models\Bulletin;
use App\Models\Contrat;
use App\Models\Employeur;
use App\Services\PaieService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Paie du mois : preparation des bulletins, ajustements exceptionnels, puis
 * validation et mise en paiement.
 */
class PaieController extends Controller
{
    use ServesModule;

    public const MODULE = 'personnel';

    public function index(Request $request, PaieService $paie): Response
    {
        $this->autoriserAcces($request->user());

        $mois = $this->mois($request);
        $annee = $this->annee($request);
        $employeurId = $request->query('employeur') ? (int) $request->query('employeur') : null;
        $statut = $request->query('statut');
        $perimetre = $this->perimetre($request);

        $bulletins = Bulletin::query()
            ->with(['agent.user', 'employeur', 'contrat'])
            ->where('mois', $mois)->where('annee', $annee)
            ->when($perimetre !== null, fn ($q) => $q->whereIn('employeur_id', $perimetre))
            ->when($employeurId, fn ($q) => $q->where('employeur_id', $employeurId))
            ->when(in_array($statut, ['brouillon', 'valide', 'paye'], true), fn ($q) => $q->where('statut', $statut))
            ->get()
            ->sortBy(fn (Bulletin $b) => mb_strtolower((string) $b->agent?->user?->fullName()))
            ->values()
            ->map(fn (Bulletin $b) => $b->toUiArray())
            ->all();

        return Inertia::render('modules/personnel/paie/index', [
            'periode' => ['mois' => $mois, 'annee' => $annee],
            'moisLibelles' => Bulletin::MOIS,
            'bulletins' => $bulletins,
            'filtres' => ['employeur' => $employeurId, 'statut' => $statut],
            'employeurs' => Employeur::where('actif', true)
                ->when($perimetre !== null, fn ($q) => $q->whereIn('id', $perimetre))
                ->orderBy('sigle')->get()
                ->map(fn ($e) => $e->toUiArray())->all(),
            'masse' => $paie->masseSalariale($mois, $annee, $employeurId, $perimetre),
            'repartition' => [
                'brouillon' => $this->compter($mois, $annee, 'brouillon', $employeurId, $perimetre),
                'valide' => $this->compter($mois, $annee, 'valide', $employeurId, $perimetre),
                'paye' => $this->compter($mois, $annee, 'paye', $employeurId, $perimetre),
            ],
            'peutGerer' => $this->peutGerer($request->user()),
        ]);
    }

    /**
     * Registre des bulletins, toutes periodes confondues : c'est ici qu'on
     * retrouve un bulletin ancien, par agent, par employeur ou par annee.
     */
    public function registre(Request $request): Response
    {
        $this->autoriserAcces($request->user());

        $recherche = trim((string) $request->query('q'));
        $annee = $request->query('annee');
        $employeurId = $request->query('employeur') ? (int) $request->query('employeur') : null;
        $statut = $request->query('statut');
        $perimetre = $this->perimetre($request);

        $bulletins = Bulletin::query()
            ->with(['agent.user', 'employeur', 'contrat'])
            ->when($perimetre !== null, fn ($q) => $q->whereIn('employeur_id', $perimetre))
            ->when($annee, fn ($q) => $q->where('annee', (int) $annee))
            ->when($employeurId, fn ($q) => $q->where('employeur_id', $employeurId))
            ->when(in_array($statut, ['brouillon', 'valide', 'paye'], true), fn ($q) => $q->where('statut', $statut))
            ->when($recherche !== '', fn ($q) => $q->whereHas('agent.user', fn ($u) => $u
                ->where('name', 'like', "%{$recherche}%")
                ->orWhere('lastname', 'like', "%{$recherche}%")
                ->orWhere('matricule', 'like', "%{$recherche}%")))
            ->orderByDesc('annee')->orderByDesc('mois')->orderBy('id')
            ->paginate(30)->withQueryString()
            ->through(fn (Bulletin $b) => $b->toUiArray());

        return Inertia::render('modules/personnel/paie/bulletins', [
            'bulletins' => $bulletins,
            'filtres' => ['q' => $recherche, 'annee' => $annee, 'employeur' => $employeurId, 'statut' => $statut],
            'employeurs' => Employeur::when($perimetre !== null, fn ($q) => $q->whereIn('id', $perimetre))
                ->orderBy('sigle')->get()->map(fn ($e) => $e->toUiArray())->all(),
            'annees' => Bulletin::when($perimetre !== null, fn ($q) => $q->whereIn('employeur_id', $perimetre))
                ->distinct()->orderByDesc('annee')->pluck('annee')->all(),
            'peutGerer' => $this->peutGerer($request->user()),
        ]);
    }

    public function show(Request $request, Bulletin $bulletin): Response
    {
        $this->autoriserAcces($request->user());
        $this->verifierEntite($request, $bulletin->employeur_id);

        $bulletin->load(['agent.user', 'employeur', 'contrat.echelon.categorie', 'contrat.profil']);

        return Inertia::render('modules/personnel/paie/bulletin', [
            'bulletin' => $bulletin->toUiArray(),
            'contrat' => $bulletin->contrat?->toUiArray(),
            'employeur' => $bulletin->employeur?->toUiArray(),
            'agent' => $bulletin->agent?->toUiArray(),
            'ajustements' => Ajustement::where('contrat_id', $bulletin->contrat_id)
                ->where('mois', $bulletin->mois)->where('annee', $bulletin->annee)
                ->get()->map(fn (Ajustement $a) => $a->toUiArray())->all(),
            'peutGerer' => $this->peutGerer($request->user()),
        ]);
    }

    /** Prepare les bulletins du mois pour un employeur, ou pour tous. */
    public function generer(Request $request, PaieService $paie): RedirectResponse
    {
        $this->autoriserGestion($request->user());

        $donnees = $request->validate([
            'mois' => ['required', 'integer', 'min:1', 'max:12'],
            'annee' => ['required', 'integer', 'min:2000', 'max:'.(date('Y') + 1)],
            'employeur_id' => ['nullable', 'exists:employeurs,id'],
        ]);

        // `nullable` ne renvoie pas la cle quand le champ est absent : sans
        // employeur designe, on prepare la paie de tout le groupe.
        $choisi = $donnees['employeur_id'] ?? null;

        $perimetre = $this->perimetre($request);

        if ($choisi) {
            $this->verifierEntite($request, (int) $choisi);
        }

        $employeurs = $choisi
            ? Employeur::where('id', $choisi)->get()
            : Employeur::where('actif', true)
                ->when($perimetre !== null, fn ($q) => $q->whereIn('id', $perimetre))
                ->get();

        $total = ['crees' => 0, 'recalcules' => 0, 'ignores' => 0];

        try {
            foreach ($employeurs as $employeur) {
                foreach ($paie->genererMois($employeur, $donnees['mois'], $donnees['annee']) as $cle => $valeur) {
                    $total[$cle] += $valeur;
                }
            }
        } catch (RuntimeException $e) {
            return back()->withErrors(['paie' => $e->getMessage()]);
        }

        return back()->with('status', trans_choice(
            '{0}Aucun bulletin à préparer.|[1,*]:crees bulletin(s) préparé(s), :recalcules recalculé(s), :ignores déjà figé(s).',
            $total['crees'] + $total['recalcules'] + $total['ignores'],
            ['crees' => $total['crees'], 'recalcules' => $total['recalcules'], 'ignores' => $total['ignores']],
        ));
    }

    public function recalculer(Request $request, Bulletin $bulletin, PaieService $paie): RedirectResponse
    {
        return $this->agir($request, $bulletin, fn () => $paie->recalculer($bulletin), __('Bulletin recalculé.'));
    }

    public function valider(Request $request, Bulletin $bulletin, PaieService $paie): RedirectResponse
    {
        return $this->agir(
            $request,
            $bulletin,
            fn () => $paie->valider($bulletin, $request->user()->id),
            __('Bulletin validé.')
        );
    }

    public function payer(Request $request, Bulletin $bulletin, PaieService $paie): RedirectResponse
    {
        return $this->agir(
            $request,
            $bulletin,
            fn () => $paie->payer($bulletin, $request->user()->id),
            __('Bulletin marqué payé.')
        );
    }

    /** Valide, ou met en paiement, toute une selection d'un coup. */
    public function traiterLot(Request $request, PaieService $paie): RedirectResponse
    {
        $this->autoriserGestion($request->user());

        $donnees = $request->validate([
            'action' => ['required', Rule::in(['valider', 'payer'])],
            'bulletins' => ['required', 'array', 'min:1'],
            'bulletins.*' => ['integer', 'exists:bulletins,id'],
        ]);

        $faits = 0;
        $refus = [];

        $perimetre = $this->perimetre($request);

        $selection = Bulletin::whereIn('id', $donnees['bulletins'])
            ->when($perimetre !== null, fn ($q) => $q->whereIn('employeur_id', $perimetre))
            ->with('agent.user')->get();

        foreach ($selection as $bulletin) {
            try {
                $donnees['action'] === 'valider'
                    ? $paie->valider($bulletin, $request->user()->id)
                    : $paie->payer($bulletin, $request->user()->id);
                $faits++;
            } catch (RuntimeException $e) {
                $refus[] = ($bulletin->agent?->user?->fullName() ?? '#'.$bulletin->id).' : '.$e->getMessage();
            }
        }

        if ($refus !== []) {
            return back()
                ->with('status', __(':n bulletin(s) traité(s).', ['n' => $faits]))
                ->withErrors(['paie' => implode(' ', array_slice($refus, 0, 3))]);
        }

        return back()->with('status', __(':n bulletin(s) traité(s).', ['n' => $faits]));
    }

    public function annoter(Request $request, Bulletin $bulletin): RedirectResponse
    {
        $this->autoriserGestion($request->user());
        $this->verifierEntite($request, $bulletin->employeur_id);

        $bulletin->update($request->validate(['note' => ['nullable', 'string', 'max:1000']]));

        return back()->with('status', __('Note enregistrée.'));
    }

    // --------------------------------------------------------- ajustements

    public function storeAjustement(Request $request, Contrat $contrat, PaieService $paie): RedirectResponse
    {
        $this->autoriserGestion($request->user());
        $this->verifierEntite($request, $contrat->employeur_id);

        $donnees = $request->validate([
            'mois' => ['required', 'integer', 'min:1', 'max:12'],
            'annee' => ['required', 'integer', 'min:2000', 'max:'.(date('Y') + 1)],
            'type' => ['required', Rule::in(['bonus', 'retenue'])],
            'mode' => ['required', Rule::in(['fixe', 'pourcentage'])],
            'libelle' => ['required', 'string', 'max:255'],
            'montant' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'motif' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($donnees['mode'] === 'pourcentage' && $donnees['montant'] > 100) {
            return back()->withErrors(['montant' => __('Un pourcentage ne dépasse pas 100.')]);
        }

        $bulletin = Bulletin::where('contrat_id', $contrat->id)
            ->where('mois', $donnees['mois'])->where('annee', $donnees['annee'])->first();

        if ($bulletin && $bulletin->statut !== 'brouillon') {
            return back()->withErrors([
                'ajustement' => __('Le bulletin de cette période est déjà figé : reportez l’ajustement au mois suivant.'),
            ]);
        }

        $contrat->ajustements()->create($donnees + ['saisi_par' => $request->user()->id]);

        // Le brouillon du mois suit immediatement.
        if ($bulletin) {
            $paie->recalculer($bulletin);
        }

        return back()->with('status', __('Ajustement enregistré.'));
    }

    public function destroyAjustement(Request $request, Ajustement $ajustement, PaieService $paie): RedirectResponse
    {
        $this->autoriserGestion($request->user());
        $this->verifierEntite($request, $ajustement->contrat?->employeur_id);

        $bulletin = Bulletin::where('contrat_id', $ajustement->contrat_id)
            ->where('mois', $ajustement->mois)->where('annee', $ajustement->annee)->first();

        if ($bulletin && $bulletin->statut !== 'brouillon') {
            return back()->withErrors([
                'ajustement' => __('Le bulletin de cette période est figé : l’ajustement ne peut plus être retiré.'),
            ]);
        }

        $ajustement->delete();

        if ($bulletin) {
            $paie->recalculer($bulletin);
        }

        return back()->with('status', __('Ajustement supprimé.'));
    }

    private function agir(Request $request, Bulletin $bulletin, callable $action, string $message): RedirectResponse
    {
        $this->autoriserGestion($request->user());
        $this->verifierEntite($request, $bulletin->employeur_id);

        try {
            $action();
        } catch (RuntimeException $e) {
            return back()->withErrors(['paie' => $e->getMessage()]);
        }

        return back()->with('status', $message);
    }

    /** Nombre de bulletins d'un statut, dans le perimetre et le filtre courants. */
    private function compter(int $mois, int $annee, string $statut, ?int $employeurId, ?array $perimetre): int
    {
        return Bulletin::where('mois', $mois)->where('annee', $annee)
            ->when($perimetre !== null, fn ($q) => $q->whereIn('employeur_id', $perimetre))
            ->when($employeurId, fn ($q) => $q->where('employeur_id', $employeurId))
            ->where('statut', $statut)->count();
    }

    private function mois(Request $request): int
    {
        $mois = (int) ($request->query('mois') ?: now()->month);

        return $mois >= 1 && $mois <= 12 ? $mois : (int) now()->month;
    }

    private function annee(Request $request): int
    {
        $annee = (int) ($request->query('annee') ?: now()->year);

        return $annee >= 2000 && $annee <= (int) date('Y') + 1 ? $annee : (int) now()->year;
    }
}
