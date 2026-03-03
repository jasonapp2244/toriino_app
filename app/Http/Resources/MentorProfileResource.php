<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MentorProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'specialization'   => $this->specialization,
            'intro'            => $this->intro,
            'intro_video'      => $this->intro_video ? asset('storage/' . $this->intro_video) : null,
            'price_per_hour'   => $this->price_per_hour,
            'languages'        => $this->languages,
            'rating'           => $this->rating,
            'total_reviews'    => $this->total_reviews,
            'is_verified'      => $this->is_verified,
            'is_featured'      => $this->is_featured,
            'experience_years' => $this->experience_years,
        ];
    }
}
