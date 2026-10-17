<?php

declare(strict_types=1);

namespace App\Application\Hotspot\Services;

use App\Infrastructure\Network\Mikrotik\Services\HotspotVoucherService;
use App\Models\HotspotProfile;
use App\Models\Router;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherBatch;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class VoucherGenerationService
{
    public function __construct(
        protected HotspotVoucherService $mikrotikVoucherService
    ) {}

    /**
     * Generate a batch of hotspot vouchers.
     *
     * @param  array{
     *     name?: string,
     *     profile_id: int,
     *     quantity: int,
     *     code_format: 'numeric'|'alphabet'|'alphanumeric'|'random_prefix',
     *     character_length: int,
     *     prefix?: string|null,
     *     password_mode: 'same_as_username'|'random_password',
     *     time_limit?: string|null,
     *     data_limit_mb?: int|null,
     *     sync_to_mikrotik?: bool
     * }  $params
     * @return array{batch: VoucherBatch, vouchers_count: int, synced_count: int}
     */
    public function generate(Router $router, User $user, array $params): array
    {
        $profile = HotspotProfile::where('router_id', $router->id)
            ->where('id', $params['profile_id'])
            ->firstOrFail();

        $quantity = max(1, min(1000, (int) $params['quantity']));
        $charLength = max(4, min(12, (int) $params['character_length']));
        $codeFormat = $params['code_format'];
        $prefix = isset($params['prefix']) ? trim((string) $params['prefix']) : '';
        $passwordMode = $params['password_mode'];
        $batchName = ! empty($params['name']) ? trim((string) $params['name']) : 'Batch-'.Carbon::now()->format('Ymd-His');
        $syncToMikrotik = $params['sync_to_mikrotik'] ?? true;

        $timeLimit = $params['time_limit'] ?? null;
        if (! $timeLimit && $profile->validity_duration && $profile->validity_unit) {
            $unitMap = ['hours' => 'h', 'days' => 'd', 'minutes' => 'm'];
            $suffix = $unitMap[$profile->validity_unit] ?? 'h';
            $timeLimit = $profile->validity_duration.$suffix;
        }

        $dataLimitBytes = ! empty($params['data_limit_mb'])
            ? (int) $params['data_limit_mb'] * 1024 * 1024
            : null;

        [$batch, $voucherRecords] = DB::transaction(function () use (
            $router,
            $user,
            $profile,
            $quantity,
            $charLength,
            $codeFormat,
            $prefix,
            $passwordMode,
            $batchName,
            $timeLimit,
            $dataLimitBytes
        ) {
            $batch = VoucherBatch::create([
                'id' => (string) Str::ulid(),
                'router_id' => $router->id,
                'profile_id' => $profile->id,
                'name' => $batchName,
                'quantity' => $quantity,
                'prefix' => $prefix !== '' ? $prefix : null,
                'character_length' => $charLength,
                'code_format' => $codeFormat,
                'password_mode' => $passwordMode,
                'created_by' => $user->id,
            ]);

            $now = Carbon::now();
            $generatedCodes = [];
            $voucherRecords = [];

            for ($i = 0; $i < $quantity; $i++) {
                $code = $this->generateUniqueCode($codeFormat, $charLength, $prefix, $generatedCodes);
                $generatedCodes[$code] = true;

                $password = $passwordMode === 'same_as_username'
                    ? $code
                    : $this->generateRandomString('alphanumeric', $charLength);

                $voucherId = (string) Str::ulid();

                $voucherRecords[] = [
                    'id' => $voucherId,
                    'batch_id' => $batch->id,
                    'router_id' => $router->id,
                    'profile_id' => $profile->id,
                    'username' => $code,
                    'password' => $password,
                    'time_limit' => $timeLimit,
                    'data_limit_bytes' => $dataLimitBytes,
                    'comment' => $batch->name,
                    'status' => 'active',
                    'is_printed' => false,
                    'printed_at' => null,
                    'first_login_at' => null,
                    'expires_at' => null,
                    'mac_address' => null,
                    'ip_address' => null,
                    'synced_to_router' => false,
                    'sync_failed_reason' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // Bulk insert in chunks of 200 for fast DB performance
            foreach (array_chunk($voucherRecords, 200) as $chunk) {
                Voucher::insert($chunk);
            }

            return [$batch, $voucherRecords];
        });

        $syncedCount = 0;

        // Sync to MikroTik RouterOS outside DB transaction to prevent holding DB locks and memory
        if ($syncToMikrotik) {
            $syncedCount = $this->syncBatchToRouter($router, $profile, $voucherRecords);
        }

        return [
            'batch' => $batch->fresh(),
            'vouchers_count' => $quantity,
            'synced_count' => $syncedCount,
        ];
    }

    /**
     * Push generated vouchers to MikroTik router /ip/hotspot/user.
     *
     * @param  array<int, array<string, mixed>>  $voucherRecords
     */
    protected function syncBatchToRouter(Router $router, HotspotProfile $profile, array $voucherRecords): int
    {
        $syncedCount = 0;
        $failedIds = [];

        foreach ($voucherRecords as $record) {
            try {
                $this->mikrotikVoucherService->addHotspotUser($router, [
                    'name' => (string) $record['username'],
                    'password' => (string) $record['password'],
                    'profile' => $profile->name,
                    'time_limit' => $record['time_limit'] ?? '',
                    'data_limit' => ! empty($record['data_limit_bytes']) ? (string) $record['data_limit_bytes'] : '',
                    'comment' => (string) $record['comment'],
                ]);
                $syncedCount++;
            } catch (\Throwable $e) {
                $failedIds[$record['id']] = $e->getMessage();
            }
        }

        // Mark synced vouchers in database
        if ($syncedCount > 0) {
            Voucher::where('batch_id', $voucherRecords[0]['batch_id'])
                ->whereNotIn('id', array_keys($failedIds))
                ->update(['synced_to_router' => true]);
        }

        foreach ($failedIds as $id => $reason) {
            Voucher::where('id', $id)->update([
                'synced_to_router' => false,
                'sync_failed_reason' => Str::limit($reason, 255),
            ]);
        }

        return $syncedCount;
    }

    /**
     * Generate unique voucher code.
     *
     * @param  array<string, bool>  $existing
     */
    protected function generateUniqueCode(string $format, int $length, string $prefix, array &$existing): string
    {
        $maxAttempts = 100;
        $attempt = 0;

        do {
            $randomPart = match ($format) {
                'numeric' => $this->generateRandomString('numeric', $length),
                'alphabet' => $this->generateRandomString('alphabet', $length),
                'alphanumeric' => $this->generateRandomString('alphanumeric', $length),
                'random_prefix' => $this->generateRandomString('alphanumeric', $length),
                default => throw new InvalidArgumentException("Unsupported code format: {$format}"),
            };

            $candidate = $prefix !== '' ? $prefix.$randomPart : $randomPart;
            $attempt++;
        } while (isset($existing[$candidate]) && $attempt < $maxAttempts);

        return $candidate;
    }

    /**
     * Generate random string based on character type.
     */
    protected function generateRandomString(string $type, int $length): string
    {
        $numericChars = '23456789'; // exclude 0 and 1 to prevent visual confusion
        $alphaChars = 'abcdefghjkmnpqrstuvwxyz'; // exclude i, l, o
        $alphanumericChars = '23456789abcdefghjkmnpqrstuvwxyz';

        $pool = match ($type) {
            'numeric' => $numericChars,
            'alphabet' => $alphaChars,
            default => $alphanumericChars,
        };

        $result = '';
        $poolLength = strlen($pool);

        for ($i = 0; $i < $length; $i++) {
            $result .= $pool[random_int(0, $poolLength - 1)];
        }

        return $result;
    }
}
