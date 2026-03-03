<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SessionBookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'status'     => $this->status,
            'session'    => new MentorSessionResource($this->whenLoaded('session')),
            'student'    => new UserResource($this->whenLoaded('student')),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
