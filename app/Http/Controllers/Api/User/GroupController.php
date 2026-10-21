<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\GroupResource;
use App\Models\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GroupController extends Controller
{
    /**
     * List all access groups with counts.
     */
    public function index(): JsonResponse
    {
        $groups = Group::with(['permissions', 'routers'])
            ->withCount('users')
            ->orderByDesc('is_superadmin')
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => GroupResource::collection($groups),
        ]);
    }

    /**
     * Store a newly created group with permissions and router scoping.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:50', 'unique:groups,slug'],
            'description' => ['nullable', 'string', 'max:500'],
            'all_routers' => ['required', 'boolean'],
            'router_ids' => ['nullable', 'array'],
            'router_ids.*' => ['integer', 'exists:routers,id'],
            'permissions' => ['nullable', 'array'],
        ]);

        $slug = ! empty($validated['slug'])
            ? Str::slug($validated['slug'])
            : Str::slug($validated['name']);

        // Ensure unique slug
        $baseSlug = $slug;
        $counter = 1;
        while (Group::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        $group = Group::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'is_superadmin' => false,
            'all_routers' => (bool) $validated['all_routers'],
        ]);

        if (! empty($validated['permissions']) && is_array($validated['permissions'])) {
            $group->syncPermissions($validated['permissions']);
        }

        if (! $group->all_routers && ! empty($validated['router_ids'])) {
            $group->routers()->sync($validated['router_ids']);
        }

        $group->load(['permissions', 'routers'])->loadCount('users');

        return response()->json([
            'status' => 'success',
            'message' => 'Group created successfully.',
            'data' => new GroupResource($group),
        ], 201);
    }

    /**
     * Display group details.
     */
    public function show(Group $group): JsonResponse
    {
        $group->load(['permissions', 'routers'])->loadCount('users');

        return response()->json([
            'status' => 'success',
            'data' => new GroupResource($group),
        ]);
    }

    /**
     * Update an existing group.
     */
    public function update(Request $request, Group $group): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:50', 'unique:groups,slug,'.$group->id],
            'description' => ['nullable', 'string', 'max:500'],
            'all_routers' => ['required', 'boolean'],
            'router_ids' => ['nullable', 'array'],
            'router_ids.*' => ['integer', 'exists:routers,id'],
            'permissions' => ['nullable', 'array'],
        ]);

        $updateData = [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ];

        if (! $group->is_superadmin) {
            if (! empty($validated['slug'])) {
                $updateData['slug'] = Str::slug($validated['slug']);
            }
            $updateData['all_routers'] = (bool) $validated['all_routers'];
        } else {
            // Superadmin always has all_routers true
            $updateData['all_routers'] = true;
        }

        $group->update($updateData);

        if (! empty($validated['permissions']) && is_array($validated['permissions']) && ! $group->is_superadmin) {
            $group->syncPermissions($validated['permissions']);
        }

        if (! $group->is_superadmin) {
            if ($group->all_routers) {
                $group->routers()->detach();
            } elseif (isset($validated['router_ids'])) {
                $group->routers()->sync($validated['router_ids']);
            }
        }

        $group->load(['permissions', 'routers'])->loadCount('users');

        return response()->json([
            'status' => 'success',
            'message' => 'Group updated successfully.',
            'data' => new GroupResource($group),
        ]);
    }

    /**
     * Delete an access group.
     */
    public function destroy(Group $group): JsonResponse
    {
        if ($group->is_superadmin || $group->slug === 'superadmin') {
            return response()->json([
                'status' => 'error',
                'code' => 'SUPERADMIN_GROUP_PROTECTED',
                'message' => 'The Super Administrator group cannot be deleted.',
            ], 403);
        }

        if ($group->users()->count() > 0) {
            return response()->json([
                'status' => 'error',
                'code' => 'GROUP_HAS_ACTIVE_USERS',
                'message' => 'Cannot delete group with assigned active users. Reassign users before deleting.',
            ], 422);
        }

        $group->routers()->detach();
        $group->permissions()->delete();
        $group->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Group deleted successfully.',
        ]);
    }
}
