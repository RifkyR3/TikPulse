<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * List all users with filtering, search, and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with('group');

        if ($search = $request->query('search')) {
            $search = (string) $search;
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        if ($request->has('group_id') && $request->query('group_id') !== null && $request->query('group_id') !== '') {
            $query->where('group_id', (int) $request->query('group_id'));
        }

        if ($request->has('is_active') && $request->query('is_active') !== null && $request->query('is_active') !== '') {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = min(100, max(5, (int) $request->query('per_page', 15)));
        $users = $query->orderBy('name')->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => UserResource::collection($users->items()),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    /**
     * Store a newly created user account.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'group_id' => ['required', 'integer', 'exists:groups,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $group = Group::findOrFail($validated['group_id']);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'group_id' => $group->id,
            'role' => $group->is_superadmin ? 'superadmin' : ($group->slug === 'admin' ? 'admin' : 'operator'),
            'is_active' => $validated['is_active'] ?? true,
            'preferred_locale' => 'en',
            'settings' => [
                'theme' => 'system',
                'accent_color' => 'sky',
                'refresh_interval' => 15,
                'table_compact_mode' => false,
            ],
        ]);

        $user->load('group');

        return response()->json([
            'status' => 'success',
            'message' => 'User created successfully.',
            'data' => new UserResource($user),
        ], 201);
    }

    /**
     * Display the specified user.
     */
    public function show(User $user): JsonResponse
    {
        $user->load('group');

        return response()->json([
            'status' => 'success',
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Update an existing user account.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'password' => ['nullable', 'string', 'min:8'],
            'group_id' => ['required', 'integer', 'exists:groups,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $currentUser = $request->user();

        // Guard: prevent deactivating own account
        if ($currentUser && $currentUser->id === $user->id && isset($validated['is_active']) && ! $validated['is_active']) {
            return response()->json([
                'status' => 'error',
                'code' => 'CANNOT_DEACTIVATE_SELF',
                'message' => 'You cannot deactivate your own account.',
            ], 422);
        }

        $targetGroup = Group::findOrFail($validated['group_id']);

        // Guard: prevent demoting or deactivating the last superadmin
        if ($user->isSuperAdmin() && (! $targetGroup->is_superadmin || (isset($validated['is_active']) && ! $validated['is_active']))) {
            $otherSuperadminsCount = User::where('id', '!=', $user->id)
                ->where('is_active', true)
                ->where(function ($q): void {
                    $q->where('role', 'superadmin')
                        ->orWhereHas('group', fn ($g) => $g->where('is_superadmin', true));
                })
                ->count();

            if ($otherSuperadminsCount === 0) {
                return response()->json([
                    'status' => 'error',
                    'code' => 'LAST_SUPERADMIN_PROTECTED',
                    'message' => 'Cannot demote or deactivate the last remaining active Super Administrator.',
                ], 422);
            }
        }

        $updateData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'group_id' => $targetGroup->id,
            'role' => $targetGroup->is_superadmin ? 'superadmin' : ($targetGroup->slug === 'admin' ? 'admin' : 'operator'),
        ];

        if (isset($validated['is_active'])) {
            $updateData['is_active'] = (bool) $validated['is_active'];
        }

        if (! empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $user->update($updateData);
        $user->load('group');

        return response()->json([
            'status' => 'success',
            'message' => 'User updated successfully.',
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Delete an existing user account.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $currentUser = $request->user();

        if ($currentUser && $currentUser->id === $user->id) {
            return response()->json([
                'status' => 'error',
                'code' => 'CANNOT_DELETE_SELF',
                'message' => 'You cannot delete your own account.',
            ], 422);
        }

        if ($user->isSuperAdmin()) {
            $otherSuperadminsCount = User::where('id', '!=', $user->id)
                ->where('is_active', true)
                ->where(function ($q): void {
                    $q->where('role', 'superadmin')
                        ->orWhereHas('group', fn ($g) => $g->where('is_superadmin', true));
                })
                ->count();

            if ($otherSuperadminsCount === 0) {
                return response()->json([
                    'status' => 'error',
                    'code' => 'LAST_SUPERADMIN_PROTECTED',
                    'message' => 'Cannot delete the last remaining active Super Administrator.',
                ], 422);
            }
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'User deleted successfully.',
        ]);
    }
}
