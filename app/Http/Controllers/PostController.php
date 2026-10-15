<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    public function show(Request $request, Post $post): Response|RedirectResponse
    {
        // Une publication masquee ou programmee n'est pas atteignable par son lien.
        abort_unless($post->isVisibleToStaff(), 404);

        /*
         * Un article ouvert au public n'a qu'une adresse : celle de la salle
         * de presse. C'est elle qu'on partage, elle qui porte les
         * metadonnees, elle qui compte les lectures.
         */
        if ($post->estPublique()) {
            return redirect()->route('info.article', $post);
        }

        $post->enregistrerVue($request);

        return Inertia::render('post', [
            'post' => $post->load(['author', 'rubrique', 'medias'])->toUiArray(),
        ]);
    }
}
