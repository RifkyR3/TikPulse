<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Resources\RouterResource;
use App\Http\Resources\UserResource;
use App\Models\Router;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Middleware;
use Laravel\Sanctum\PersonalAccessToken;

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
        /** @var User|null $user */
        $user = $request->user();

        // If not authenticated via session, check if Sanctum token cookie is present
        if (! $user && $request->hasCookie('tikpulse_token')) {
            $tokenString = $request->cookie('tikpulse_token');
            if (is_string($tokenString)) {
                $accessToken = PersonalAccessToken::findToken($tokenString);
                if ($accessToken && $accessToken->tokenable instanceof User) {
                    $user = $accessToken->tokenable;
                    if ($request->hasSession()) {
                        Auth::login($user);
                    }
                }
            }
        }

        if ($user) {
            $user->loadMissing(['group', 'group.permissions', 'group.routers']);
        }

        // Get list of routers (scoped to group if not all_routers)
        $routersQuery = Router::query()
            ->orderBy('name');

        if ($user && ! $user->canAccessAllRouters()) {
            $allowedIds = $user->getAllowedRouterIds() ?? [];
            $routersQuery->whereIn('id', $allowedIds);
        }

        $routers = $user ? RouterResource::collection($routersQuery->get())->resolve() : [];

        // Active router ID from session or header
        $activeRouterId = (int) ($request->session()->get('active_router_id') ?? ($routers[0]['id'] ?? 0));

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? (new UserResource($user))->resolve() : null,
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
