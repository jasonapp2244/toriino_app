<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'phone'     => $this->phone,
            'photo'     => $this->photo ? asset('storage/' . $this->photo) : null,
            'language'  => $this->language,
            'bio'       => $this->bio,
            'city'      => $this->city,
            'country'   => $this->country,
        ];
    }
}
