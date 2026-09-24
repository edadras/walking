<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class SubmitWalkingSessionRequest extends FormRequest
{
    public function rules(): array
    {
        return WalkingSessionRules::rules();
    }
}
