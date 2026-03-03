<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'student_id'          => $this->student_id,
            'interests'           => $this->interests,
            'education_level'     => $this->education_level,
            'courses_completed'   => $this->courses_completed,
            'certificates_earned' => $this->certificates_earned,
        ];
    }
}
