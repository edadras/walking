<?php

namespace App\Http\Resources\V1;

use App\Models\PointTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PointTransaction */
class PointTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'group' => $this->type->group(),
            'amount' => $this->amount,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'description' => $this->description,
            'balance_after' => $this->balance_after,
            'rial_value' => abs($this->amount) * $this->rial_rate,
            'available_at' => $this->available_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
