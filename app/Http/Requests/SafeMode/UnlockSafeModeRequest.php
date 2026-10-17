<?php

declare(strict_types=1);

namespace App\Http\Requests\SafeMode;

use Illuminate\Foundation\Http\FormRequest;

class UnlockSafeModeRequest extends FormRequest
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
            'password' => ['required', 'string'],
            'verification_text' => ['required', 'string', 'in:UNLOCK,unlock'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
