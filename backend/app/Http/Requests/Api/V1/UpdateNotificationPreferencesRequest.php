<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\NotificationCategory;
use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'preferences' => ['required', 'array', 'min:1'],
            'preferences.*' => ['boolean'],
        ];
    }

    public function after(): array
    {
        return [function ($validator) {
            $allowed = array_column(NotificationCategory::cases(), 'value');
            foreach (array_keys((array) $this->input('preferences')) as $key) {
                if (! in_array($key, $allowed, true)) {
                    $validator->errors()->add('preferences', 'دسته اعلان نامعتبر است.');
                }
            }
        }];
    }
}
