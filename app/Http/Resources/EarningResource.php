<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EarningResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'amount'         => $this->amount,
            'type'           => $this->type,
            'description'    => $this->description,
            'reference_id'   => $this->reference_id,
            'reference_type' => $this->reference_type,
            'created_at'     => $this->created_at?->toDateTimeString(),
        ];
    }
}
