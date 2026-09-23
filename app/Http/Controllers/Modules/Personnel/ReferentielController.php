<?php

namespace App\Http\Controllers\Modules\Personnel;

use App\Http\Controllers\Concerns\ServesModule;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\CategorieRh;
use App\Models\Echelon;
use App\Models\Employeur;
use App\Models\Indemnite;
use App\Models\ProfilSalaire;
use App\Models\Retenue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Les briques dont la paie se sert : employeurs, grille des categories et
 * echelons, indemnites, retenues, et les profils qui les assemblent.
 */
class ReferentielController extends Controller
{
    use ServesModule;

    public const MODULE = 'personnel';

    public function employeurs(Request $request): Response
    {
        return $this->ecran($request, 'employeurs');
    }

    public function categories(Request $request): Response
    {
        return $this->ecran($request, 'categories');
    }

    public function profils(Request $request): Response
    {
        return $this->ecran($request, 'profils');
    }

    public function indemnites(Request $request): Response
    {
        return $this->ecran($request, 'indemnites');
    }

    public function retenues(Request $request): Response
    {
        return $this->ecran($request, 'retenues');
    }

    /**
     * Les cinq referentiels partagent un ecran : un profil se construit a
     * partir des echelons, des indemnites et des retenues, il lui faut donc
     * tout sous la main de toute facon.
     */
    private function ecran(Request $request, string $section): Response
    {
        $this->autoriserGestion($request->user());

        // Un gestionnaire ne voit que ses entites ; la grille, les indemnites,
        // les retenues et les profils restent communs a tout le groupe.
        $perimetre = $this->perimetre($request);

        return Inertia::render('modules/personnel/referentiels', [
            'section' => $section,
            'estAdministrateur' => $request->user()->isAdmin(),
            'employeurs' => Employeur::with('application')
                ->withCount(['contrats as contratsActifs' => fn ($q) => $q->where('statut', 'actif')])
                ->when($perimetre !== null, fn ($q) => $q->whereIn('id', $perimetre))
                ->orderBy('sigle')->get()
                ->map(fn (Employeur $e) => $e->toUiArray() + [
                    'applicationId' => $e->application_id,
                    'application' => $e->application?->name,
                    'contratsActifs' => (int) $e->contratsActifs,
                ])->all(),
            'applications' => Application::where('type', 'application')->orderBy('name')->get()
                ->map(fn ($a) => ['id' => $a->id, 'name' => $a->name])->all(),
            'categories' => CategorieRh::with(['echelons' => fn ($q) => $q->orderBy('numero')])
                ->orderBy('libelle')->get()
                ->map(fn (CategorieRh $c) => $c->toUiArray() + [
                    'echelons' => $c->echelons->map(fn (Echelon $e) => $e->toUiArray())->all(),
                ])->all(),
            'indemnites' => Indemnite::orderBy('libelle')->get()->map(fn ($i) => $i->toUiArray())->all(),
            'retenues' => Retenue::orderBy('libelle')->get()->map(fn ($r) => $r->toUiArray())->all(),
            'profils' => ProfilSalaire::with(['echelon.categorie', 'categorie', 'indemnites', 'retenues'])
                ->withCount(['contrats as contratsActifs' => fn ($q) => $q->where('statut', 'actif')])
                ->orderBy('nom')->get()
                ->map(fn (ProfilSalaire $p) => $p->toUiArray() + ['contratsActifs' => (int) $p->contratsActifs])->all(),
        ]);
    }

    // ---------------------------------------------------------- employeurs

    public function storeEmployeur(Request $request): RedirectResponse
    {
        $this->autoriserGestion($request->user());
        $this->reserveALAdministration($request);

        Employeur::create($this->reglesEmployeur($request));

        return back()->with('status', __('Employeur ajouté.'));
    }

    public function updateEmployeur(Request $request, Employeur $employeur): RedirectResponse
    {
        $this->autoriserGestion($request->user());
        $this->reserveALAdministration($request);

        $employeur->update($this->reglesEmployeur($request, $employeur));

        return back()->with('status', __('Employeur mis à jour.'));
    }

    public function destroyEmployeur(Request $request, Employeur $employeur): RedirectResponse
    {
        $this->autoriserGestion($request->user());
        $this->reserveALAdministration($request);

        if ($employeur->contrats()->exists()) {
            return back()->withErrors([
                'employeur' => __('Des contrats sont rattachés à cet employeur : désactivez-le plutôt.'),
            ]);
        }

        $employeur->delete();

        return back()->with('status', __('Employeur supprimé.'));
    }

    /** @return array<string, mixed> */
    private function reglesEmployeur(Request $request, ?Employeur $employeur = null): array
    {
        return $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'sigle' => ['required', 'string', 'max:20', Rule::unique('employeurs', 'sigle')->ignore($employeur)],
            'application_id' => ['nullable', 'exists:applications,id'],
            'niu' => ['nullable', 'string', 'max:40'],
            'numero_cnps' => ['nullable', 'string', 'max:40'],
            'banque' => ['nullable', 'string', 'max:255'],
            'compte_bancaire' => ['nullable', 'string', 'max:60'],
            'signataire' => ['nullable', 'string', 'max:255'],
            'actif' => ['boolean'],
        ]);
    }

    /**
     * Les entites du groupe se creent et se suppriment depuis l'administration
     * du portail : un gestionnaire travaille dans les siennes, il n'en ajoute
     * pas.
     */
    private function reserveALAdministration(Request $request): void
    {
        abort_unless(
            $request->user()->isAdmin(),
            403,
            __("Seul un administrateur du portail peut modifier les entités du groupe.")
        );
    }

    // ---------------------------------------------- categories et echelons

    public function storeCategorie(Request $request): RedirectResponse
    {
        $this->autoriserGestion($request->user());

        CategorieRh::create($this->reglesCategorie($request));

        return back()->with('status', __('Catégorie ajoutée.'));
    }

    public function updateCategorie(Request $request, CategorieRh $categorie): RedirectResponse
    {
        $this->autoriserGestion($request->user());

        $categorie->update($this->reglesCategorie($request));

        return back()->with('status', __('Catégorie mise à jour.'));
    }

    public function destroyCategorie(Request $request, CategorieRh $categorie): RedirectResponse
    {
        $this->autoriserGestion($request->user());

        if ($categorie->echelons()->whereHas('contrats')->exists()) {
            return back()->withErrors(['categorie' => __('Des contrats utilisent ses échelons.')]);
        }

        $categorie->delete();

        return back()->with('status', __('Catégorie supprimée.'));
    }

    /** @return array<string, mixed> */
    private function reglesCategorie(Request $request): array
    {
        return $request->validate([
            'libelle' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'actif' => ['boolean'],
        ]);
    }

    public function storeEchelon(Request $request, CategorieRh $categorie): RedirectResponse
    {
        $this->autoriserGestion($request->user());

        $categorie->echelons()->create($this->reglesEchelon($request, $categorie));

        return back()->with('status', __('Échelon ajouté.'));
    }

    public function updateEchelon(Request $request, Echelon $echelon): RedirectResponse
    {
        $this->autoriserGestion($request->user());

        $echelon->update($this->reglesEchelon($request, $echelon->categorie, $echelon));

        return back()->with('status', __('Échelon mis à jour.'));
    }

    public function destroyEchelon(Request $request, Echelon $echelon): RedirectResponse
    {
        $this->autoriserGestion($request->user());

        if ($echelon->contrats()->exists() || $echelon->profils()->exists()) {
            return back()->withErrors(['echelon' => __('Cet échelon est utilisé : désactivez-le plutôt.')]);
        }

        $echelon->delete();

        return back()->with('status', __('Échelon supprimé.'));
    }

    /** @return array<string, mixed> */
    private function reglesEchelon(Request $request, CategorieRh $categorie, ?Echelon $echelon = null): array
    {
        return $request->validate([
            'numero' => [
                'required', 'integer', 'min:1', 'max:60',
                Rule::unique('echelons', 'numero')
                    ->where('categorie_rh_id', $categorie->id)->ignore($echelon),
            ],
            'libelle' => ['nullable', 'string', 'max:255'],
            'salaire' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'anciennete_min' => ['nullable', 'integer', 'min:0', 'max:50'],
            'actif' => ['boolean'],
        ]);
    }

    // ------------------------------------------- indemnites et retenues

    public function storeIndemnite(Request $request): RedirectResponse
    {
        $this->autoriserGestion($request->user());

        Indemnite::create($request->validate([
            'libelle' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'imposable' => ['boolean'],
            'actif' => ['boolean'],
        ]));

        return back()->with('status', __('Indemnité ajoutée.'));
    }

    public function updateIndemnite(Request $request, Indemnite $indemnite): RedirectResponse
    {
        $this->autoriserGestion($request->user());

        $indemnite->update($request->validate([
            'libelle' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'imposable' => ['boolean'],
            'actif' => ['boolean'],
        ]));

        return back()->with('status', __('Indemnité mise à jour.'));
    }

    public function destroyIndemnite(Request $request, Indemnite $indemnite): RedirectResponse
    {
        $this->autoriserGestion($request->user());

        $indemnite->delete();

        return back()->with('status', __('Indemnité supprimée.'));
    }

    public function storeRetenue(Request $request): RedirectResponse
    {
        $this->autoriserGestion($request->user());

        Retenue::create($request->validate([
            'libelle' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'actif' => ['boolean'],
        ]));

        return back()->with('status', __('Retenue ajoutée.'));
    }

    public function updateRetenue(Request $request, Retenue $retenue): RedirectResponse
    {
        $this->autoriserGestion($request->user());

        $retenue->update($request->validate([
            'libelle' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'actif' => ['boolean'],
        ]));

        return back()->with('status', __('Retenue mise à jour.'));
    }

    public function destroyRetenue(Request $request, Retenue $retenue): RedirectResponse
    {
        $this->autoriserGestion($request->user());

        $retenue->delete();

        return back()->with('status', __('Retenue supprimée.'));
    }

    // -------------------------------------------------------------- profils

    public function storeProfil(Request $request): RedirectResponse
    {
        $this->autoriserGestion($request->user());

        $donnees = $this->reglesProfil($request);
        $profil = ProfilSalaire::create($donnees['profil']);
        $this->synchroniserLignes($profil, $donnees);

        return back()->with('status', __('Profil de salaire créé.'));
    }

    public function updateProfil(Request $request, ProfilSalaire $profil): RedirectResponse
    {
        $this->autoriserGestion($request->user());

        $donnees = $this->reglesProfil($request);
        $profil->update($donnees['profil']);
        $this->synchroniserLignes($profil, $donnees);

        return back()->with('status', __('Profil mis à jour. Les bulletins déjà édités ne changent pas.'));
    }

    public function destroyProfil(Request $request, ProfilSalaire $profil): RedirectResponse
    {
        $this->autoriserGestion($request->user());

        if ($profil->contrats()->exists()) {
            return back()->withErrors(['profil' => __('Des contrats utilisent ce profil : désactivez-le plutôt.')]);
        }

        $profil->delete();

        return back()->with('status', __('Profil supprimé.'));
    }

    /** @return array{profil: array<string, mixed>, indemnites: array, retenues: array} */
    private function reglesProfil(Request $request): array
    {
        $valide = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'categorie_rh_id' => ['nullable', 'exists:categories_rh,id'],
            'echelon_id' => ['nullable', 'exists:echelons,id'],
            'actif' => ['boolean'],
            'indemnites' => ['array'],
            'indemnites.*.id' => ['required', 'exists:indemnites,id'],
            'indemnites.*.type_calcul' => ['required', Rule::in(['fixe', 'pourcentage'])],
            'indemnites.*.valeur' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'retenues' => ['array'],
            'retenues.*.id' => ['required', 'exists:retenues,id'],
            'retenues.*.type_calcul' => ['required', Rule::in(['fixe', 'pourcentage'])],
            'retenues.*.valeur' => ['required', 'numeric', 'min:0', 'max:99999999'],
        ]);

        return [
            'profil' => collect($valide)->only(['nom', 'description', 'categorie_rh_id', 'echelon_id', 'actif'])->all(),
            'indemnites' => $valide['indemnites'] ?? [],
            'retenues' => $valide['retenues'] ?? [],
        ];
    }

    /** @param  array{indemnites: array, retenues: array}  $donnees */
    private function synchroniserLignes(ProfilSalaire $profil, array $donnees): void
    {
        $profil->indemnites()->sync(collect($donnees['indemnites'])
            ->mapWithKeys(fn ($l) => [$l['id'] => ['type_calcul' => $l['type_calcul'], 'valeur' => $l['valeur']]])
            ->all());

        $profil->retenues()->sync(collect($donnees['retenues'])
            ->mapWithKeys(fn ($l) => [$l['id'] => ['type_calcul' => $l['type_calcul'], 'valeur' => $l['valeur']]])
            ->all());
    }
}
