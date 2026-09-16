<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesMediaUploads;
use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    use HandlesMediaUploads;

    public function index(): Response
    {
        return Inertia::render('admin/posts/index', [
            'posts' => Post::with('author')->latest('created_at')->paginate(15)
                ->through(fn ($post) => $post->toUiArray()),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/posts/form', ['post' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $post = Post::create($this->validated($request) + ['author_id' => $request->user()->id]);

        return redirect()->route('admin.posts.index')->with('status', "« {$post->title} » a été publiée.");
    }

    public function edit(Post $post): Response
    {
        return Inertia::render('admin/posts/form', ['post' => $post->toUiArray()]);
    }

    public function update(Request $request, Post $post): RedirectResponse
    {
        $post->update($this->validated($request, $post));

        return redirect()->route('admin.posts.index')->with('status', "« {$post->title} » a été mise à jour.");
    }

    public function destroy(Post $post): RedirectResponse
    {
        $title = $post->title;
        $this->deleteUploaded($post->image);
        $post->delete();

        return redirect()->route('admin.posts.index')->with('status', "« {$title} » a été supprimée.");
    }

    /**
     * Bascule l'affichage depuis la liste, sans ouvrir la fiche.
     */
    public function toggleVisibility(Post $post): RedirectResponse
    {
        $post->forceFill(['is_visible' => ! $post->is_visible])->save();

        return back()->with('status', $post->is_visible
            ? __('« :titre » est de nouveau visible sur le portail.', ['titre' => $post->title])
            : __('« :titre » est masquée du portail.', ['titre' => $post->title]));
    }

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
