<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MentorSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'title'            => $this->title,
            'type'             => $this->type,
            'start_time'       => $this->start_time?->toDateTimeString(),
            'end_time'         => $this->end_time?->toDateTimeString(),
            'duration_minutes' => $this->duration_minutes,
            'max_seats'        => $this->max_seats,
            'seats_booked'     => $this->seats_booked,
            'seats_available'  => $this->seatsAvailable(),
            'language'         => $this->language,
            'price'            => $this->price,
            'status'           => $this->status,
            'mentor'           => new UserResource($this->whenLoaded('mentor')),
            'bookings_count'   => $this->whenLoaded('bookings', fn() => $this->bookings->count()),
            'created_at'       => $this->created_at?->toDateTimeString(),
        ];
    }
}
