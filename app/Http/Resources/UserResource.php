<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'email'      => $this->email,
            'role'       => $this->whenLoaded('roles', fn() => $this->getRoleNames()->first()),
            'profile'    => new UserProfileResource($this->whenLoaded('profile')),
            'mentor_profile'  => new MentorProfileResource($this->whenLoaded('mentorProfile')),
            'teacher_profile' => new TeacherProfileResource($this->whenLoaded('teacherProfile')),
            'student_profile' => new StudentProfileResource($this->whenLoaded('studentProfile')),
            'created_at' => $this->created_at?->toDateString(),
        ];
    }
}
