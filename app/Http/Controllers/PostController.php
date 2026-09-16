<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    public function show(Post $post): Response
    {
        // Une publication masquee ou programmee n'est pas atteignable par son lien.
        abort_unless($post->isVisibleToStaff(), 404);

        $post->increment('views');

        return Inertia::render('post', ['post' => $post->load('author')->toUiArray()]);
    }
}
