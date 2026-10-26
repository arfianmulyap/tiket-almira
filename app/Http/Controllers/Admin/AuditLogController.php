<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Read-only audit trail (Phase 12, PRD FR-12).
 *
 * The whole screen is Super Admin only, enforced by AuditLogPolicy. There is
 * no create, edit, or delete route: rows are appended by AuditLogger at the
 * moment an operation happens, and an editable trail would not be a trail.
 */
class AuditLogController extends Controller
{
    /**
     * Rows per page. Large enough to scan an incident, small enough that the
     * metadata of every row still loads quickly.
     */
    private const PER_PAGE = 25;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', AuditLog::class);

        $filters = $this->validatedFilters($request);

        $logs = AuditLog::query()
            // Subject labels and links would otherwise issue one query per
            // row; the morph eager load groups them by type.
            ->with(['user', 'auditable'])
            ->when($filters['action'], fn ($query, $action) => $query->where('action', $action))
            ->when($filters['actor'], function ($query, string $actor): void {
                // Escape LIKE wildcards so "100%" searches for that literal
                // text instead of matching every row.
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $actor);

                $query->where('user_name', 'like', '%'.$escaped.'%');
            })
            ->when($filters['from'], fn ($query, $from) => $query->where('created_at', '>=', $from.' 00:00:00'))
            ->when($filters['to'], fn ($query, $to) => $query->where('created_at', '<=', $to.' 23:59:59'))
            // Newest first. The id tiebreaker keeps rows written in the same
            // second in a stable order across pages.
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.audit-logs.index', [
            'logs' => $logs,
            'filters' => $filters,
            'actions' => AuditAction::cases(),
        ]);
    }

    public function show(AuditLog $auditLog): View
    {
        $this->authorize('view', $auditLog);

        $auditLog->load(['user', 'auditable']);

        return view('admin.audit-logs.show', [
            'log' => $auditLog,
        ]);
    }

    /**
     * Validate and normalise the list filters.
     *
     * An unknown action falls back to "all" instead of raising an error, so a
     * stale bookmark still opens a usable page — the same behaviour the order
     * list uses for its status filter.
     *
     * @return array{action: ?string, actor: ?string, from: ?string, to: ?string}
     */
    private function validatedFilters(Request $request): array
    {
        $validated = $request->validate([
            'action' => ['nullable', 'string'],
            'actor' => ['nullable', 'string', 'max:120'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $action = $validated['action'] ?? null;

        if (! in_array($action, AuditAction::values(), true)) {
            $action = null;
        }

        return [
            'action' => $action,
            'actor' => $validated['actor'] ?? null,
            'from' => $validated['from'] ?? null,
            'to' => $validated['to'] ?? null,
        ];
    }
}
