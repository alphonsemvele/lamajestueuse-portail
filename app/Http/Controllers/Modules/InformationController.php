<?php

namespace App\Http\Controllers\Modules;

use App\Http\Controllers\Concerns\HandlesMediaUploads;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\Rubrique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Centre d'information : module interne du portail.
 *
 * Consultation ouverte aux employes qui ont la tuile, redaction reservee aux
 * roles declares dans config/modules.php pour ce module.
 */
class InformationController extends Controller
{
    use HandlesMediaUploads;

    private const KEY = 'informations';

    public function index(Request $request): Response
    {
        $this->authorizeAccess($request);

        $gere = $this->canManage($request);
        $recherche = trim((string) $request->query('q'));
        $rubrique = $request->query('rubrique');
        $etat = $request->query('etat');

        $posts = Post::query()
            ->with(['author', 'rubrique'])
            ->when(! $gere, fn ($query) => $query->published())
            ->when($rubrique, fn ($query, $slug) => $query->whereHas('rubrique', fn ($r) => $r->where('slug', $slug)))
            ->when($etat === 'publie', fn ($query) => $query->published())
            ->when($etat === 'brouillon', fn ($query) => $query->whereNull('published_at'))
            ->when($etat === 'masque', fn ($query) => $query->where('is_visible', false))
            ->when($etat === 'public', fn ($query) => $query->where('is_public', true))
            ->when($recherche !== '', fn ($query) => $query->where(
                fn ($sub) => $sub->where('title', 'like', "%{$recherche}%")->orWhere('excerpt', 'like', "%{$recherche}%")
            ))
            ->orderByRaw('published_at IS NULL DESC')
            ->latest('published_at')
            ->paginate(9)
            ->withQueryString()
            ->through(fn (Post $post) => $post->toUiArray());

        return Inertia::render('modules/informations/index', [
            'posts' => $posts,
            'filters' => ['q' => $recherche, 'rubrique' => $rubrique, 'etat' => $etat],
            'canManage' => $gere,
            'rubriques' => $this->rubriquesUi(),
            'lienPublic' => route('info.index'),
            // Ce que la redaction regarde : la portee de ce qu'elle publie.
            'mesures' => [
                'publies' => Post::published()->count(),
                'publics' => Post::publique()->count(),
                'vues' => (int) Post::published()->sum('views'),
                'partages' => (int) Post::published()->sum('partages_whatsapp')
                    + (int) Post::published()->sum('partages_facebook')
                    + (int) Post::published()->sum('partages_lien'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorizeManagement($request);

        return Inertia::render('modules/informations/form', [
            'post' => null,
            'rubriques' => $this->rubriquesUi(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManagement($request);

        $post = Post::create($this->validated($request) + ['author_id' => $request->user()->id]);
        $this->rangerLesPieces($request, $post);

        return redirect()->route('informations.edit', $post)
            ->with('status', __('« :titre » a été publiée.', ['titre' => $post->title]));
    }

    public function edit(Request $request, Post $post): Response
    {
        $this->authorizeManagement($request);

        return Inertia::render('modules/informations/form', [
            'post' => $post->load(['author', 'rubrique', 'medias'])->toUiArray(),
            'rubriques' => $this->rubriquesUi(),
            'lienPublic' => $post->estPublique() ? route('info.article', $post) : null,
        ]);
    }

    public function update(Request $request, Post $post): RedirectResponse
    {
        $this->authorizeManagement($request);

        $post->update($this->validated($request, $post));
        $this->rangerLesPieces($request, $post);

        return back()
            ->with('status', __('« :titre » a été mise à jour.', ['titre' => $post->title]));
    }

    public function destroy(Request $request, Post $post): RedirectResponse
    {
        $this->authorizeManagement($request);

        $titre = $post->title;
        $this->deleteUploaded($post->image);

        // Les pieces et les lectures partent avec l'article : le modele s'en
        // charge, fichiers compris.
        $post->delete();

        return redirect()->route('informations.index')
            ->with('status', __('« :titre » a été supprimée.', ['titre' => $titre]));
    }

    public function toggleVisibility(Request $request, Post $post): RedirectResponse
    {
        $this->authorizeManagement($request);

        $post->forceFill(['is_visible' => ! $post->is_visible])->save();

        return back()->with('status', $post->is_visible
            ? __('« :titre » est de nouveau visible sur le portail.', ['titre' => $post->title])
            : __('« :titre » est masquée du portail.', ['titre' => $post->title]));
    }

    /**
     * Ajoute les pieces jointes envoyees avec le formulaire : les images
     * nourrissent la galerie, les documents se telechargent.
     */
    private function rangerLesPieces(Request $request, Post $post): void
    {
        $request->validate([
            'images' => ['nullable', 'array', 'max:12'],
            'images.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:6144'],
            'documents' => ['nullable', 'array', 'max:12'],
            'documents.*' => ['file', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt', 'max:15360'],
        ], [
            'images.*.image' => 'Les images doivent être des images (jpg, png, webp).',
            'images.*.max' => 'Chaque image doit rester sous 6 Mo.',
            'documents.*.mimes' => 'Documents acceptés : PDF, Word, Excel, PowerPoint.',
            'documents.*.max' => 'Chaque document doit rester sous 15 Mo.',
        ]);

        $rang = (int) $post->medias()->max('ordre');

        foreach (['image' => 'images', 'document' => 'documents'] as $genre => $champ) {
            foreach ((array) $request->file($champ, []) as $fichier) {
                $post->medias()->create([
                    'genre' => $genre,
                    'chemin' => $fichier->store('publications/'.$post->id, 'public'),
                    'nom' => Str::limit($fichier->getClientOriginalName(), 200, ''),
                    'taille' => $fichier->getSize(),
                    'mime' => $fichier->getMimeType(),
                    'ordre' => ++$rang,
                ]);
            }
        }
    }

    public function supprimerPiece(Request $request, PostMedia $media): RedirectResponse
    {
        $this->authorizeManagement($request);

        Storage::disk('public')->delete($media->chemin);
        $media->delete();

        return back()->with('status', __('La pièce a été retirée.'));
    }

    public function legenderPiece(Request $request, PostMedia $media): RedirectResponse
    {
        $this->authorizeManagement($request);

        $media->update($request->validate([
            'legende' => ['nullable', 'string', 'max:220'],
        ]));

        return back();
    }

    // ------------------------------------------------------------ rubriques

    public function rubriques(Request $request): Response
    {
        $this->authorizeManagement($request);

        return Inertia::render('modules/informations/rubriques', [
            'rubriques' => Rubrique::ordonnee()
                ->withCount('posts as articles_count')
                ->get()
                ->map(fn (Rubrique $rubrique) => $rubrique->toUiArray())
                ->all(),
            'familles' => Rubrique::FAMILLES,
        ]);
    }

    public function storeRubrique(Request $request): RedirectResponse
    {
        $this->authorizeManagement($request);

        $rubrique = Rubrique::create($this->rubriqueValidee($request));

        return back()->with('status', __('La rubrique « :nom » a été créée.', ['nom' => $rubrique->nom]));
    }

    public function updateRubrique(Request $request, Rubrique $rubrique): RedirectResponse
    {
        $this->authorizeManagement($request);

        $rubrique->update($this->rubriqueValidee($request, $rubrique));

        return back()->with('status', __('La rubrique « :nom » a été mise à jour.', ['nom' => $rubrique->nom]));
    }

    public function destroyRubrique(Request $request, Rubrique $rubrique): RedirectResponse
    {
        $this->authorizeManagement($request);

        // Une rubrique qui porte des articles ne disparait pas sous eux.
        if ($rubrique->posts()->exists()) {
            return back()->withErrors([
                'rubrique' => __('« :nom » porte des articles : déplacez-les avant de la supprimer.', ['nom' => $rubrique->nom]),
            ]);
        }

        $nom = $rubrique->nom;
        $rubrique->delete();

        return back()->with('status', __('La rubrique « :nom » a été supprimée.', ['nom' => $nom]));
    }

    /**
     * @return array<string, mixed>
     */
    private function rubriqueValidee(Request $request, ?Rubrique $rubrique = null): array
    {
        $donnees = $request->validate([
            'nom' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:220'],
            'couleur' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'famille' => ['required', Rule::in(array_keys(Rubrique::FAMILLES))],
            'ordre' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        $donnees['ordre'] = $donnees['ordre'] ?? 0;
        $donnees['actif'] = $request->boolean('actif', true);
        $donnees['slug'] = $rubrique?->slug ?: Str::slug($donnees['nom']).'-'.Str::lower(Str::random(4));

        return $donnees;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rubriquesUi(): array
    {
        return Rubrique::ordonnee()
            ->withCount('posts as articles_count')
            ->get()
            ->map(fn (Rubrique $rubrique) => $rubrique->toUiArray())
            ->all();
    }

    private function module(): ?Application
    {
        return Application::active()->where('module_key', self::KEY)->first();
    }

    /**
     * L'employe doit avoir la tuile du module, comme pour toute application.
     */
    private function authorizeAccess(Request $request): void
    {
        $module = $this->module();

        abort_if($module === null, 404);

        abort_unless(
            $request->user()->isAdmin()
                || $request->user()->applications()->where('applications.id', $module->id)->exists(),
            403,
            __("Vous n'avez pas accès à cette application.")
        );
    }

    private function canManage(Request $request): bool
    {
        $module = $this->module();

        return $module !== null && $module->allowsManagementBy($request->user());
    }

    private function authorizeManagement(Request $request): void
    {
        $this->authorizeAccess($request);

        abort_unless($this->canManage($request), 403, __("Vous n'êtes pas autorisé à publier dans le centre d'information."));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Post $post = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'excerpt' => ['nullable', 'string', 'max:400'],
            'body' => ['nullable', 'string'],
            'image' => ['nullable', 'string', 'max:255'],
            'image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'cover_legende' => ['nullable', 'string', 'max:220'],
            'rubrique_id' => ['required', Rule::exists('rubriques', 'id')],
            'published_at' => ['nullable', 'date'],
        ]);

        $rubrique = Rubrique::findOrFail($data['rubrique_id']);

        $data['slug'] = $post?->slug ?: Str::slug($data['title']).'-'.Str::lower(Str::random(5));
        $data['is_featured'] = $request->boolean('is_featured');
        $data['is_visible'] = $request->boolean('is_visible');
        $data['is_public'] = $request->boolean('is_public');

        /*
         * La famille de la rubrique garnit l'ancienne colonne « type » : le
         * tableau de bord et l'administration classent encore par la.
         */
        $data['type'] = $rubrique->famille;

        unset($data['image_file']);
        $data['image'] = $this->resolveMedia($request, $post?->image, 'image', 'publications');

        return $data;
    }
}
