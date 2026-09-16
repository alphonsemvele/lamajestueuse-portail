<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $applications = $user->applications()
            ->with('category')
            ->where('applications.is_active', true)
            ->orderBy('application_user.is_pinned', 'desc')
            ->orderBy('applications.sort_order')
            ->orderBy('applications.name')
            ->get();

        $search = trim((string) $request->query('q'));
        $categoryFilter = $request->query('category');

        if ($search !== '') {
            $applications = $applications->filter(
                fn ($app) => str_contains(mb_strtolower($app->name.' '.$app->description), mb_strtolower($search))
            );
        }

        if ($categoryFilter) {
            $applications = $applications->filter(fn ($app) => $app->category?->slug === $categoryFilter);
        }

        $checkIn = $user->checkIns()->whereDate('work_date', today())->first();

        $posts = fn (string $type) => Post::published()->where('type', $type)
            ->latest('published_at')->take(5)->get()->map->toUiArray()->all();

        return Inertia::render('dashboard', [
            // Les modules du portail prennent place parmi les applications.
            'apps' => $applications->whereIn('type', ['application', 'module'])->values()->map->toUiArray()->all(),
            'quickLinks' => $applications->where('type', 'quick_link')->values()->map->toUiArray()->all(),
            'categories' => $user->applications()->with('category')->get()
                ->pluck('category')->filter()->unique('id')->values()
                ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'slug' => $c->slug, 'color' => $c->color])
                ->all(),
            'checkIn' => $checkIn ? [
                'checkedInAt' => $checkIn->checked_in_at?->format('H:i'),
                'checkedOutAt' => $checkIn->checked_out_at?->format('H:i'),
            ] : null,
            'today' => now()->translatedFormat('D j M'),
            'news' => $posts('news'),
            'announcements' => $posts('announcement'),
            'billboard' => $posts('billboard'),
            'filters' => ['q' => $search, 'category' => $categoryFilter],
        ]);
    }
}
