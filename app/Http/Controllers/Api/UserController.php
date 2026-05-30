<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreUserRequest;
use App\Http\Requests\Api\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::with(['roles', 'branch']);

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('email', 'like', "%{$term}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('role')) {
            $query->whereHas('roles', fn($q) => $q->where('name', $request->role));
        }

        $perPage = $request->get('per_page', 15);
        $users = $query->orderBy('name')->paginate($perPage);

        $users->getCollection()->transform(function (User $user) {
            return $this->formatUser($user);
        });

        return response()->json([
            'success' => true,
            'message' => 'Users retrieved successfully',
            'data'    => $users,
        ]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'password'  => Hash::make($data['password']),
            'branch_id' => $data['branch_id'] ?? null,
            'status'    => $data['status'] ?? 'active',
        ]);

        $user->syncRoles([$data['role']]);

        return response()->json([
            'success' => true,
            'message' => 'User created successfully',
            'data'    => $this->formatUser($user->fresh(['roles', 'branch'])),
        ], 201);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'User retrieved successfully',
            'data'    => $this->formatUser($user->load(['roles', 'branch'])),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();

        $fields = collect($data)->only(['name', 'email', 'status', 'branch_id'])->toArray();
        if (array_key_exists('branch_id', $data)) {
            $fields['branch_id'] = $data['branch_id'];
        }
        if (!empty($data['password'])) {
            $fields['password'] = Hash::make($data['password']);
        }

        $user->update($fields);

        if (!empty($data['role'])) {
            $user->syncRoles([$data['role']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'data'    => $this->formatUser($user->fresh(['roles', 'branch'])),
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($request->user()->id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete your own account.',
                'data'    => null,
            ], 422);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully',
            'data'    => null,
        ]);
    }

    private function formatUser(User $user): array
    {
        return [
            'id'         => $user->id,
            'name'       => $user->name,
            'email'      => $user->email,
            'status'     => $user->status,
            'role'       => $user->roles->first()?->name,
            'roles'      => $user->roles->pluck('name'),
            'branch_id'  => $user->branch_id,
            'branch'     => $user->branch,
            'created_at' => $user->created_at,
        ];
    }
}
