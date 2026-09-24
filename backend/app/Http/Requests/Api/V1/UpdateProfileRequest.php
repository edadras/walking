<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function rules(): array
    {
        $year = (int) now()->year;

        return [
            'display_name' => ['sometimes', 'nullable', 'string', 'min:2', 'max:30', 'regex:/^[\p{L}\p{M}\p{N} ._-]+$/u'],
            'birth_year' => ['sometimes', 'nullable', 'integer', 'between:'.($year - 100).','.($year - 10)],
            'gender' => ['sometimes', 'nullable', 'in:female,male'],
            'height_cm' => ['sometimes', 'nullable', 'integer', 'between:100,230'],
            'weight_kg' => ['sometimes', 'nullable', 'numeric', 'between:25,250'],
            'timezone' => ['sometimes', 'timezone:all'],
        ];
    }

    public function messages(): array
    {
        return ['display_name.regex' => 'نام نمایشی فقط می‌تواند شامل حروف، عدد و فاصله باشد.'];
    }
}
