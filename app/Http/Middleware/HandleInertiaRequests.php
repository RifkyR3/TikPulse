<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Router;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        // Get list of active routers
        $routers = $user
            ? Router::query()
                ->select(['id', 'name', 'ip_address', 'status', 'is_active', 'is_safe_mode_enabled', 'board_name', 'ros_version'])
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
            : [];

        // Active router ID from session or header
        $activeRouterId = (int) ($request->session()->get('active_router_id') ?? ($routers[0]->id ?? 0));

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'preferred_locale' => $user->preferred_locale ?? 'en',
                    'avatar_url' => $user->avatar_url,
                ] : null,
            ],
            'routers' => $routers,
            'activeRouterId' => $activeRouterId,
            'locale' => $user->preferred_locale ?? $request->session()->get('locale', config('app.locale', 'en')),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'status' => fn () => $request->session()->get('status'),
            ],
        ];
    }
}
