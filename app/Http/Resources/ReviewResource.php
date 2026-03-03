<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'rating'     => $this->rating,
            'comment'    => $this->comment,
            'from_user'  => new UserResource($this->whenLoaded('fromUser')),
            'session_id' => $this->session_id,
            'course_id'  => $this->course_id,
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
