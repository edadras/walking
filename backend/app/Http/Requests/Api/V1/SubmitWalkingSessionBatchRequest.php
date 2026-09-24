<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Only the envelope is validated here; each session is validated individually
 * so one malformed item doesn't block the rest of an offline backlog.
 */
class SubmitWalkingSessionBatchRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'sessions' => ['required', 'array', 'min:1', 'max:20'],
            'sessions.*' => ['array'],
            'sessions.*.client_session_id' => ['required', 'uuid'],
        ];
    }
}
