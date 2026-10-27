<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Role management (Phase 10).
 *
 * Every action is authorised through RolePolicy, which reads the permission of
 * the acting admin's own role. A role's permissions take effect on the next
 * request because nothing here or in the authorisation layer caches them.
 */
class RoleController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        $this->authorize('viewAny', Role::class);

        return view('admin.roles.index', [
            'roles' => Role::query()
                // The protected role is listed first so the permanent way back
                // into the panel is always visible.
                ->orderByDesc('is_super_admin')
                ->orderBy('name')
                ->withCount(['permissions', 'users'])
                ->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Role::class);

        return view('admin.roles.create', $this->formData(new Role, []));
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $this->authorize('create', Role::class);

        $data = $request->validated();

        $role = DB::transaction(function () use ($request, $data): Role {
            $role = new Role;

            $role->fill([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
            ]);

            $role->save();

            $role->permissions()->sync($data['permissions'] ?? []);

            $this->audit->record($role, AuditAction::RoleCreated, $request->user(), [
                'name' => $role->name,
                'permissions' => $role->permissionNames(),
            ]);

            return $role;
        });

        return redirect()
            ->route('admin.roles.index')
            ->with('success', "Role {$role->name} berhasil dibuat.");
    }

    public function edit(Role $role): View
    {
        $this->authorize('update', $role);

        $role->load('permissions');

        return view('admin.roles.edit', $this->formData($role, $role->permissions->pluck('id')->all()));
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $this->authorize('update', $role);

        $data = $request->validated();

        DB::transaction(function () use ($request, $role, $data): void {
            $role->loadMissing('permissions');

            $previousName = $role->name;
            $before = $role->permissionNames();

            $role->fill([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
            ]);

            $role->save();

            $role->permissions()->sync($data['permissions'] ?? []);

            // The audit entry must describe the state that is now in the
            // database, so the relation is dropped before reading it back.
            $role->unsetRelation('permissions');
            $after = $role->permissionNames();

            $this->audit->record($role, AuditAction::RoleUpdated, $request->user(), [
                'name' => $role->name,
                'previous_name' => $previousName,
            ]);

            if ($before !== $after) {
                $this->audit->record($role, AuditAction::RolePermissionsChanged, $request->user(), [
                    'before' => $before,
                    'after' => $after,
                ]);
            }
        });

        return redirect()
            ->route('admin.roles.index')
            ->with('success', "Role {$role->name} berhasil diperbarui.");
    }

    /**
     * Delete an unused role.
     *
     * A role still assigned to an admin is refused rather than cascaded: the
     * admins would silently lose all access, and their accounts belong to the
     * Phase 11 account management flow.
     */
    public function destroy(Request $request, Role $role): RedirectResponse
    {
        $this->authorize('delete', $role);

        $members = $role->users()->count();

        if ($members > 0) {
            return redirect()
                ->route('admin.roles.index')
                ->with('error', "Role {$role->name} masih dipakai {$members} akun admin dan tidak dapat dihapus.");
        }

        $name = $role->name;

        DB::transaction(function () use ($request, $role): void {
            // Captured before the delete: the pivot rows are removed with the
            // role, but the trail has to keep what access was revoked.
            $metadata = [
                'name' => $role->name,
                'permissions' => $role->permissionNames(),
            ];

            $role->delete();

            $this->audit->record($role, AuditAction::RoleDeleted, $request->user(), $metadata);
        });

        return redirect()
            ->route('admin.roles.index')
            ->with('success', "Role {$name} berhasil dihapus.");
    }

    /**
     * @param  array<int, int>  $selected
     * @return array<string, mixed>
     */
    private function formData(Role $role, array $selected): array
    {
        return [
            'role' => $role,
            'selected' => $selected,
            // Grouped for the permission matrix; ordered so the same groups
            // and rows appear in a stable order on every visit.
            'permissionGroups' => Permission::query()
                ->orderBy('group_name')
                ->orderBy('label')
                ->get()
                ->groupBy('group_name'),
        ];
    }
}
