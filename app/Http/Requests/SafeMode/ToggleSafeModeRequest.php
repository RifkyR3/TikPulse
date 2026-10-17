<?php

declare(strict_types=1);

namespace App\Http\Requests\SafeMode;

use Illuminate\Foundation\Http\FormRequest;

class ToggleSafeModeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'password' => ['required_if:enabled,false', 'string'],
        ];
    }
}
