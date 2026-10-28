<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation by normalizing the login identifier.
     */
    protected function prepareForValidation(): void
    {
        $identifier = trim((string) ($this->input('login') ?? $this->input('email') ?? $this->input('username') ?? ''));

        $this->merge([
            'login' => $identifier,
            'email' => $identifier,
            'username' => $identifier,
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'login' => ['required', 'string'],
            'email' => ['required', 'string'],
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get the login identifier (email or username).
     */
    public function getIdentifier(): string
    {
        return trim((string) ($this->input('login') ?? $this->input('email') ?? $this->input('username') ?? ''));
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Clear the login rate limiter.
     */
    public function clearRateLimiter(): void
    {
        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Increment the login rate limiter attempts.
     */
    public function hitRateLimiter(): void
    {
        RateLimiter::hit($this->throttleKey(), 60);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->getIdentifier()).'|'.$this->ip());
    }
}
