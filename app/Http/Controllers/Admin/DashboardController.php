<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccessLog;
use App\Models\Application;
use App\Models\Post;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/dashboard', [
            'stats' => [
                'applications' => Application::where('type', 'application')->count(),
                'quickLinks' => Application::where('type', 'quick_link')->count(),
                'users' => User::count(),
                'activeUsers' => User::where('status', 'active')->count(),
                'posts' => Post::count(),
                'opensToday' => AccessLog::where('action', 'open')->whereDate('created_at', today())->count(),
            ],
            'topApps' => Application::withCount(['users'])
                ->orderByDesc('users_count')->take(5)->get()->map->toUiArray()->all(),
            'recentLogs' => AccessLog::with(['user', 'application'])
                ->latest()->take(12)->get()->map(fn ($log) => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'user' => $log->user?->fullName(),
                    'application' => $log->application?->name,
                    'ip' => $log->ip_address,
                    'ago' => $log->created_at->diffForHumans(),
                ])->all(),
        ]);
    }
}
