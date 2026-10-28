<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\VerifyEmailCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_request_verification_notification_with_code(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'operator@tikpulse.test',
            'email_verified_at' => null,
            'role' => 'operator',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/email/verification-notification');

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
            ]);

        // Assert notification sent with 6-digit code
        Notification::assertSentTo($user, VerifyEmailCodeNotification::class, function ($notification) use ($user) {
            $cached = Cache::get("email_verify_code_{$user->id}");

            return strlen($notification->code) === 6
                && is_array($cached)
                && $cached['code'] === $notification->code
                && $cached['email'] === $user->email;
        });
    }

    public function test_user_can_request_verification_notification_with_new_email(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'old@tikpulse.test',
            'email_verified_at' => now(),
            'role' => 'customer',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/email/verification-notification', [
                'email' => 'brandnew@tikpulse.test',
            ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
            ]);

        $user->refresh();
        $this->assertSame('brandnew@tikpulse.test', $user->email);
        $this->assertNull($user->email_verified_at);

        Notification::assertSentTo($user, VerifyEmailCodeNotification::class, function ($notification) use ($user) {
            $cached = Cache::get("email_verify_code_{$user->id}");

            return is_array($cached)
                && $cached['email'] === 'brandnew@tikpulse.test';
        });
    }

    public function test_user_can_verify_email_with_valid_6_digit_code_via_api(): void
    {
        $user = User::factory()->create([
            'email' => 'operator@tikpulse.test',
            'email_verified_at' => null,
            'role' => 'operator',
        ]);

        $code = '654321';
        Cache::put("email_verify_code_{$user->id}", [
            'code' => $code,
            'email' => $user->email,
        ], now()->addMinutes(15));

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/email/verify-code', [
                'code' => $code,
            ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'is_email_verified' => true,
                ],
            ]);

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertNull(Cache::get("email_verify_code_{$user->id}"));
    }

    public function test_user_cannot_verify_with_invalid_code(): void
    {
        $user = User::factory()->create([
            'email' => 'operator@tikpulse.test',
            'email_verified_at' => null,
        ]);

        Cache::put("email_verify_code_{$user->id}", [
            'code' => '123456',
            'email' => $user->email,
        ], now()->addMinutes(15));

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/email/verify-code', [
                'code' => '999999',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
            ]);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_user_cannot_verify_with_expired_code(): void
    {
        $user = User::factory()->create([
            'email' => 'operator@tikpulse.test',
            'email_verified_at' => null,
        ]);

        // No cache present

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/email/verify-code', [
                'code' => '123456',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
            ]);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_user_can_verify_email_via_web_session(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@tikpulse.test',
            'email_verified_at' => null,
            'role' => 'admin',
        ]);

        $code = '112233';
        Cache::put("email_verify_code_{$user->id}", [
            'code' => $code,
            'email' => $user->email,
        ], now()->addMinutes(15));

        $response = $this->actingAs($user, 'web')
            ->post('/email/verify-code', [
                'code' => $code,
            ]);

        $response->assertRedirect('/dashboard');
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_verification_messages_are_localized_for_indonesian_and_english(): void
    {
        // 1. Indonesian user
        $userIndo = User::factory()->create([
            'email' => 'indo@tikpulse.test',
            'email_verified_at' => null,
            'preferred_locale' => 'id',
        ]);

        $resIndo = $this->actingAs($userIndo, 'sanctum')
            ->postJson('/api/v1/email/verification-notification');

        $resIndo->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => 'Kode verifikasi telah dikirim ke email Anda! Periksa kotak masuk atau Mailpit.',
            ]);

        // Code verify success in ID
        $code = '123456';
        Cache::put("email_verify_code_{$userIndo->id}", [
            'code' => $code,
            'email' => $userIndo->email,
        ], now()->addMinutes(15));

        $verifyIndo = $this->actingAs($userIndo, 'sanctum')
            ->postJson('/api/v1/email/verify-code', ['code' => $code]);

        $verifyIndo->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => 'Alamat email Anda berhasil diverifikasi!',
            ]);

        // 2. English user
        $userEn = User::factory()->create([
            'email' => 'en@tikpulse.test',
            'email_verified_at' => null,
            'preferred_locale' => 'en',
        ]);

        $resEn = $this->actingAs($userEn, 'sanctum')
            ->postJson('/api/v1/email/verification-notification');

        $resEn->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => 'Verification code sent to your email! Check your inbox or Mailpit.',
            ]);

        // Code verify success in EN
        $codeEn = '654321';
        Cache::put("email_verify_code_{$userEn->id}", [
            'code' => $codeEn,
            'email' => $userEn->email,
        ], now()->addMinutes(15));

        $verifyEn = $this->actingAs($userEn, 'sanctum')
            ->postJson('/api/v1/email/verify-code', ['code' => $codeEn]);

        $verifyEn->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => 'Email address successfully verified!',
            ]);
    }
}
