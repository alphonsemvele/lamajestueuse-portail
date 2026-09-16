<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccessLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccessLogController extends Controller
{
    public function index(Request $request): Response
    {
        $logs = AccessLog::with(['user', 'application'])
            ->when($request->query('action'), fn ($q, $action) => $q->where('action', $action))
            ->latest()
            ->paginate(30)
            ->withQueryString()
            ->through(fn ($log) => [
                'id' => $log->id,
                'action' => $log->action,
                'user' => $log->user?->fullName(),
                'application' => $log->application?->name,
                'ip' => $log->ip_address,
                'date' => $log->created_at->format('d/m/Y H:i'),
            ]);

        return Inertia::render('admin/logs/index', [
            'logs' => $logs,
            'filters' => ['action' => $request->query('action')],
        ]);
    }
}
