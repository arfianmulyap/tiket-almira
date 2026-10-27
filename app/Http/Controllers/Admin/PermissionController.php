<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminPermission;
use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePermissionRequest;
use App\Http\Requests\Admin\UpdatePermissionRequest;
use App\Models\Permission;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Permission catalog management (Phase 10).
 *
 * The catalog of permission keys is defined in code (App\Enums\AdminPermission)
 * because every key must be checked by a backend action. This screen manages
 * the mirrored rows so a definition can be restored, relabelled, or removed
 * without touching the code.
 */
class PermissionController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        $this->authorize('viewAny', Permission::class);

        return view('admin.permissions.index', [
            'permissionGroups' => Permission::query()
                ->orderBy('group_name')
                ->orderBy('label')
                ->withCount('roles')
                ->get()
                ->groupBy('group_name'),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Permission::class);

        $registered = Permission::query()->pluck('name')->all();

        return view('admin.permissions.create', [
            // Only keys that are currently missing: restoring is the only
            // creation path, because the catalog itself lives in code.
            'available' => array_values(array_filter(
                AdminPermission::cases(),
                fn (AdminPermission $permission): bool => ! in_array($permission->value, $registered, true),
            )),
        ]);
    }

    public function store(StorePermissionRequest $request): RedirectResponse
    {
        $this->authorize('create', Permission::class);

        $permission = AdminPermission::from($request->validated('name'));

        $model = DB::transaction(function () use ($request, $permission): Permission {
            $model = new Permission;

            // Written explicitly rather than filled: the key is what the
            // backend checks, so it may never come from arbitrary input.
            $model->name = $permission->value;
            $model->label = $permission->label();
            $model->group_name = $permission->group();
            $model->description = $permission->description();

            $model->save();

            $this->audit->record($model, AuditAction::PermissionCreated, $request->user(), [
                'name' => $model->name,
            ]);

            return $model;
        });

        return redirect()
            ->route('admin.permissions.index')
            ->with('success', "Permission {$model->name} berhasil dipulihkan.");
    }

    public function edit(Permission $permission): View
    {
        $this->authorize('update', $permission);

        return view('admin.permissions.edit', [
            'permission' => $permission,
        ]);
    }

    public function update(UpdatePermissionRequest $request, Permission $permission): RedirectResponse
    {
        $this->authorize('update', $permission);

        $data = $request->validated();
        $previousLabel = $permission->label;

        DB::transaction(function () use ($request, $permission, $data, $previousLabel): void {
            $permission->fill([
                'label' => $data['label'],
                'description' => $data['description'] ?? null,
            ]);

            $permission->save();

            $this->audit->record($permission, AuditAction::PermissionUpdated, $request->user(), [
                'name' => $permission->name,
                'previous_label' => $previousLabel,
                'label' => $permission->label,
            ]);
        });

        return redirect()
            ->route('admin.permissions.index')
            ->with('success', "Permission {$permission->name} berhasil diperbarui.");
    }

    /**
     * Delete a definition that no role currently holds.
     *
     * Deleting a granted permission would silently revoke access from every
     * role at once, so the assignment has to be removed deliberately first.
     */
    public function destroy(Request $request, Permission $permission): RedirectResponse
    {
        $this->authorize('delete', $permission);

        $granted = $permission->roles()->count();

        if ($granted > 0) {
            return redirect()
                ->route('admin.permissions.index')
                ->with('error', "Permission {$permission->name} masih diberikan ke {$granted} role. Lepas dari role terlebih dahulu.");
        }

        $name = $permission->name;

        DB::transaction(function () use ($request, $permission): void {
            $permission->delete();

            $this->audit->record($permission, AuditAction::PermissionDeleted, $request->user(), [
                'name' => $permission->name,
            ]);
        });

        return redirect()
            ->route('admin.permissions.index')
            ->with('success', "Permission {$name} berhasil dihapus.");
    }
}
