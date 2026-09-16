<?php

namespace App\Http\Controllers\Modules;

use App\Http\Controllers\Concerns\HandlesMediaUploads;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $search = trim((string) $request->query('q'));
        $type = $request->query('type');

        $posts = Post::query()
            ->with('author')
            ->when(! $this->canManage($request), fn ($query) => $query->published())
            ->when($type, fn ($query, $value) => $query->where('type', $value))
            ->when($search !== '', fn ($query) => $query->where(
                fn ($sub) => $sub->where('title', 'like', "%{$search}%")->orWhere('excerpt', 'like', "%{$search}%")
            ))
            ->orderByRaw('published_at IS NULL DESC')
            ->latest('published_at')
            ->paginate(9)
            ->withQueryString()
            ->through(fn (Post $post) => $post->toUiArray());

        return Inertia::render('modules/informations/index', [
            'posts' => $posts,
            'filters' => ['q' => $search, 'type' => $type],
            'canManage' => $this->canManage($request),
            'counts' => [
                'news' => Post::published()->where('type', 'news')->count(),
                'announcement' => Post::published()->where('type', 'announcement')->count(),
                'billboard' => Post::published()->where('type', 'billboard')->count(),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorizeManagement($request);

        return Inertia::render('modules/informations/form', ['post' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManagement($request);

        $post = Post::create($this->validated($request) + ['author_id' => $request->user()->id]);

        return redirect()->route('informations.index')
            ->with('status', __('« :titre » a été publiée.', ['titre' => $post->title]));
    }

    public function edit(Request $request, Post $post): Response
    {
        $this->authorizeManagement($request);

        return Inertia::render('modules/informations/form', ['post' => $post->toUiArray()]);
    }

    public function update(Request $request, Post $post): RedirectResponse
    {
        $this->authorizeManagement($request);

        $post->update($this->validated($request, $post));

        return redirect()->route('informations.index')
            ->with('status', __('« :titre » a été mise à jour.', ['titre' => $post->title]));
    }

    public function destroy(Request $request, Post $post): RedirectResponse
    {
        $this->authorizeManagement($request);

        $titre = $post->title;
        $this->deleteUploaded($post->image);
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
            'type' => ['required', Rule::in(['news', 'announcement', 'billboard'])],
            'published_at' => ['nullable', 'date'],
        ]);

        $data['slug'] = $post?->slug ?: Str::slug($data['title']).'-'.Str::lower(Str::random(5));
        $data['is_featured'] = $request->boolean('is_featured');
        $data['is_visible'] = $request->boolean('is_visible');

        unset($data['image_file']);
        $data['image'] = $this->resolveMedia($request, $post?->image, 'image', 'publications');

        return $data;
    }
}
