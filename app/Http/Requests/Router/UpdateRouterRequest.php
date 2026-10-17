<?php

declare(strict_types=1);

namespace App\Http\Requests\Router;

use App\Domain\Router\Enums\RouterProtocol;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRouterRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:100'],
            'host' => ['sometimes', 'string', 'max:255'],
            'api_port' => ['sometimes', 'integer', 'min:1', 'max:65535'],
            'protocol' => ['sometimes', 'string', Rule::enum(RouterProtocol::class)],
            'username' => ['sometimes', 'string', 'max:100'],
            'password' => ['nullable', 'string', 'max:255'],
            'is_safe_mode_enabled' => ['sometimes', 'boolean'],
            'use_tls' => ['sometimes', 'boolean'],
            'timeout_seconds' => ['sometimes', 'integer', 'min:1', 'max:30'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
