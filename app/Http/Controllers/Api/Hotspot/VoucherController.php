<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Hotspot;

use App\Application\Hotspot\Services\VoucherGenerationService;
use App\Application\SafeMode\Services\MikrotikSafeModeGuard;
use App\Domain\SafeMode\Enums\DestructiveAction;
use App\Http\Controllers\Controller;
use App\Infrastructure\Network\Mikrotik\Services\HotspotVoucherService;
use App\Models\Router;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherBatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class VoucherController extends Controller
{
    public function __construct(
        protected VoucherGenerationService $generationService,
        protected HotspotVoucherService $mikrotikVoucherService,
        protected MikrotikSafeModeGuard $safeModeGuard
    ) {}

    /**
     * List vouchers with filters & pagination.
     */
    public function index(Request $request, Router $router): JsonResponse
    {
        $query = Voucher::with(['batch:id,name', 'profile:id,name,selling_price,rate_limit,validity_duration,validity_unit'])
            ->where('router_id', $router->id)
            ->orderByDesc('created_at');

        if ($request->filled('batch_id')) {
            $query->where('batch_id', $request->query('batch_id'));
        }

        if ($request->filled('profile_id')) {
            $query->where('profile_id', $request->query('profile_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where('username', 'ilike', "%{$search}%");
        }

        $vouchers = $query->paginate(25);

        return response()->json([
            'status' => 'success',
            'data' => $vouchers,
        ]);
    }

    /**
     * List voucher batches for router.
     */
    public function batches(Router $router): JsonResponse
    {
        $batches = VoucherBatch::with(['profile:id,name,selling_price,validity_duration,validity_unit', 'creator:id,name'])
            ->withCount('vouchers')
            ->where('router_id', $router->id)
            ->orderByDesc('created_at')
            ->paginate(15);

        return response()->json([
            'status' => 'success',
            'data' => $batches,
        ]);
    }

    /**
     * Get single batch with all vouchers for print engine.
     */
    public function batchDetail(Router $router, VoucherBatch $batch): JsonResponse
    {
        if ($batch->router_id !== $router->id) {
            return response()->json([
                'status' => 'error',
                'message' => __('vouchers.batch_not_found'),
            ], 404);
        }

        $batch->load([
            'profile',
            'creator:id,name',
            'vouchers' => fn ($q) => $q->orderBy('username'),
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $batch,
        ]);
    }

    /**
     * Generate new voucher batch.
     */
    public function generate(Request $request, Router $router): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
            'profile_id' => ['required', 'integer', 'exists:hotspot_profiles,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'code_format' => ['required', 'in:numeric,alphabet,alphanumeric,random_prefix'],
            'character_length' => ['required', 'integer', 'min:4', 'max:12'],
            'prefix' => ['nullable', 'string', 'max:10'],
            'password_mode' => ['required', 'in:same_as_username,random_password'],
            'time_limit' => ['nullable', 'string', 'max:20'],
            'data_limit_mb' => ['nullable', 'integer', 'min:1'],
            'sync_to_mikrotik' => ['nullable', 'boolean'],
        ]);

        /** @var User $user */
        $user = $request->user();

        /** @var 'numeric'|'alphabet'|'alphanumeric'|'random_prefix' $codeFormat */
        $codeFormat = $validated['code_format'];
        /** @var 'same_as_username'|'random_password' $passwordMode */
        $passwordMode = $validated['password_mode'];

        $result = $this->generationService->generate($router, $user, [
            'name' => $validated['name'] ?? null,
            'profile_id' => (int) $validated['profile_id'],
            'quantity' => (int) $validated['quantity'],
            'code_format' => $codeFormat,
            'character_length' => (int) $validated['character_length'],
            'prefix' => $validated['prefix'] ?? null,
            'password_mode' => $passwordMode,
            'time_limit' => $validated['time_limit'] ?? null,
            'data_limit_mb' => isset($validated['data_limit_mb']) ? (int) $validated['data_limit_mb'] : null,
            'sync_to_mikrotik' => $validated['sync_to_mikrotik'] ?? true,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('vouchers.generated', [
                'vouchers_count' => $result['vouchers_count'],
                'synced_count' => $result['synced_count'],
            ]),
            'data' => $result,
        ], 201);
    }

    /**
     * Mark vouchers as printed.
     */
    public function markPrinted(Request $request, Router $router): JsonResponse
    {
        $validated = $request->validate([
            'batch_id' => ['nullable', 'string'],
            'voucher_ids' => ['nullable', 'array'],
            'voucher_ids.*' => ['string'],
        ]);

        $query = Voucher::where('router_id', $router->id);

        if (! empty($validated['batch_id'])) {
            $query->where('batch_id', $validated['batch_id']);
        } elseif (! empty($validated['voucher_ids'])) {
            $query->whereIn('id', $validated['voucher_ids']);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => __('vouchers.provide_batch_or_ids'),
            ], 422);
        }

        $count = $query->update([
            'is_printed' => true,
            'printed_at' => Carbon::now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('vouchers.marked_printed', ['count' => $count]),
        ]);
    }

    /**
     * Bulk delete vouchers (Safe Mode Protected).
     */
    public function bulkDelete(Request $request, Router $router): JsonResponse
    {
        $validated = $request->validate([
            'batch_id' => ['nullable', 'string'],
            'voucher_ids' => ['nullable', 'array'],
            'voucher_ids.*' => ['string'],
        ]);

        $bypassToken = $request->header('X-SafeMode-Bypass') ?? $request->input('bypass_token');

        $this->safeModeGuard->enforce(
            router: $router,
            action: DestructiveAction::BulkDeleteVouchers,
            endpoint: $request->path(),
            payload: $validated,
            bypassToken: is_string($bypassToken) ? $bypassToken : null
        );

        $query = Voucher::where('router_id', $router->id);
        $batchName = null;

        if (! empty($validated['batch_id'])) {
            $batch = VoucherBatch::where('router_id', $router->id)->where('id', $validated['batch_id'])->first();
            $batchName = $batch?->name;
            $vouchers = $query->where('batch_id', $validated['batch_id'])->get();
        } elseif (! empty($validated['voucher_ids'])) {
            $vouchers = $query->whereIn('id', $validated['voucher_ids'])->get();
        } else {
            return response()->json([
                'status' => 'error',
                'message' => __('vouchers.provide_ids_to_delete'),
            ], 422);
        }

        $usernames = $vouchers->pluck('username')->all();

        // Optimized bulk deletion: avoids N round-trip print queries and socket exhaustion
        try {
            $this->mikrotikVoucherService->bulkRemoveHotspotUsers(
                router: $router,
                usernames: $usernames,
                comment: $batchName
            );
        } catch (\Throwable) {
            // Continue with database deletion even if router communication fails
        }

        // Delete from database
        $deletedCount = Voucher::whereIn('id', $vouchers->pluck('id'))->delete();

        if (! empty($validated['batch_id'])) {
            VoucherBatch::where('id', $validated['batch_id'])->delete();
        }

        return response()->json([
            'status' => 'success',
            'message' => __('vouchers.deleted', ['count' => $deletedCount]),
        ]);
    }
}
