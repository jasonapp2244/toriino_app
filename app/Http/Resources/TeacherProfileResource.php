<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'subject'          => $this->subject,
            'degree'           => $this->degree,
            'experience_years' => $this->experience_years,
            'languages'        => $this->languages,
            'rating'           => $this->rating,
            'total_reviews'    => $this->total_reviews,
            'is_verified'      => $this->is_verified,
            'intro'            => $this->intro,
            'intro_video'      => $this->intro_video ? asset('storage/' . $this->intro_video) : null,
        ];
    }
}
