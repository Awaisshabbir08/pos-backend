<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreRoleRequest;
use App\Http\Requests\Api\UpdateRoleRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    private const PROTECTED_ROLES = ['admin'];

    public function index(\Illuminate\Http\Request $request): JsonResponse
    {
        $query = Role::with('permissions')->orderBy('name');

        // The 'super-admin' role is platform-owner-only. Non-super-admin users
        // (tenant admins, etc.) must never see it in the role list — otherwise
        // they'd be able to assign it when creating users, which would let
        // them escalate someone to platform-level access.
        $user = $request->user();
        if (!$user || !$user->hasRole('super-admin')) {
            $query->where('name', '!=', 'super-admin');
        }

        $roles = $query->get();
        $counts = $this->userCountsByRole();

        return response()->json([
            'success' => true,
            'message' => 'Roles retrieved successfully',
            'data'    => $roles->map(fn(Role $role) => $this->formatRole($role, $counts[$role->id] ?? 0)),
        ]);
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $data = $request->validated();

        $role = Role::create([
            'name'       => $data['name'],
            'guard_name' => 'web',
        ]);

        if (!empty($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Role created successfully',
            'data'    => $this->formatRole($role->load('permissions'), 0),
        ], 201);
    }

    public function show(Role $role): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Role retrieved successfully',
            'data'    => $this->formatRole($role->load('permissions'), $this->countUsersForRole($role)),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $data = $request->validated();

        if (in_array($role->name, self::PROTECTED_ROLES) && isset($data['name']) && $data['name'] !== $role->name) {
            return response()->json([
                'success' => false,
                'message' => "The '{$role->name}' role cannot be renamed.",
                'data'    => null,
            ], 422);
        }

        if (isset($data['name'])) {
            $role->update(['name' => $data['name']]);
        }

        if (array_key_exists('permissions', $data)) {
            if (in_array($role->name, self::PROTECTED_ROLES)) {
                return response()->json([
                    'success' => false,
                    'message' => "The '{$role->name}' role's permissions cannot be modified.",
                    'data'    => null,
                ], 422);
            }
            $role->syncPermissions($data['permissions'] ?? []);
        }

        return response()->json([
            'success' => true,
            'message' => 'Role updated successfully',
            'data'    => $this->formatRole($role->fresh(['permissions']), $this->countUsersForRole($role)),
        ]);
    }

    public function destroy(Role $role): JsonResponse
    {
        if (in_array($role->name, self::PROTECTED_ROLES)) {
            return response()->json([
                'success' => false,
                'message' => "The '{$role->name}' role cannot be deleted.",
                'data'    => null,
            ], 422);
        }

        if ($this->countUsersForRole($role) > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete a role that has users assigned. Reassign or remove those users first.',
                'data'    => null,
            ], 422);
        }

        $role->delete();

        return response()->json([
            'success' => true,
            'message' => 'Role deleted successfully',
            'data'    => null,
        ]);
    }

    private function formatRole(Role $role, int $usersCount): array
    {
        return [
            'id'          => $role->id,
            'name'        => $role->name,
            'guard_name'  => $role->guard_name,
            'permissions' => $role->permissions->pluck('name'),
            'users_count' => $usersCount,
            'is_protected'=> in_array($role->name, self::PROTECTED_ROLES),
            'created_at'  => $role->created_at,
        ];
    }

    private function pivotTable(): string
    {
        return config('permission.table_names.model_has_roles') ?: 'model_has_roles';
    }

    private function pivotRoleKey(): string
    {
        return config('permission.column_names.role_pivot_key') ?: 'role_id';
    }

    private function userCountsByRole(): array
    {
        $key = $this->pivotRoleKey();

        return DB::table($this->pivotTable())
            ->where('model_type', User::class)
            ->select($key, DB::raw('COUNT(*) as total'))
            ->groupBy($key)
            ->pluck('total', $key)
            ->toArray();
    }

    private function countUsersForRole(Role $role): int
    {
        return (int) DB::table($this->pivotTable())
            ->where('model_type', User::class)
            ->where($this->pivotRoleKey(), $role->id)
            ->count();
    }
}
