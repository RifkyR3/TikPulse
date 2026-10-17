<?php

declare(strict_types=1);

namespace App\Http\Requests\Router;

use App\Domain\Router\Enums\RouterProtocol;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRouterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'host' => ['required', 'string', 'max:255'],
            'api_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'protocol' => ['required', 'string', Rule::enum(RouterProtocol::class)],
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'max:255'],
            'is_safe_mode_enabled' => ['sometimes', 'boolean'],
            'use_tls' => ['sometimes', 'boolean'],
            'timeout_seconds' => ['sometimes', 'integer', 'min:1', 'max:30'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
