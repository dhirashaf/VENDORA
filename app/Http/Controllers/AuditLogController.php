<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Log aktivitas pengguna (FR-23, ISO/IEC 27001).
 */
class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $logs = AuditLog::query()
            ->with('user')
            ->when($request->string('action')->toString(), fn (Builder $query, string $action) => $query->where('action', $action))
            ->when($request->string('entity')->toString(), fn (Builder $query, string $entity) => $query->where('entity_name', $entity))
            ->when($request->integer('user_id'), fn (Builder $query, int $userId) => $query->where('user_id', $userId))
            ->when($request->date('from'), fn (Builder $query, $from) => $query->where('created_at', '>=', $from->startOfDay()))
            ->when($request->date('to'), fn (Builder $query, $to) => $query->where('created_at', '<=', $to->endOfDay()))
            ->latest('created_at')
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('audit-logs.index', [
            'logs' => $logs,
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
            'entities' => AuditLog::query()->distinct()->orderBy('entity_name')->pluck('entity_name'),
            'users' => User::query()->orderBy('full_name')->get(['id', 'full_name']),
            'filters' => $request->only(['action', 'entity', 'user_id', 'from', 'to']),
        ]);
    }
}
