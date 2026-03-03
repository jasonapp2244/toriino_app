<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'title'             => $this->title,
            'description'       => $this->description,
            'category'          => $this->category,
            'language'          => $this->language,
            'duration'          => $this->duration,
            'price'             => $this->price,
            'rating'            => $this->rating,
            'total_enrollments' => $this->total_enrollments,
            'status'            => $this->status,
            'thumbnail'         => $this->thumbnail ? asset('storage/' . $this->thumbnail) : null,
            'intro_video'       => $this->intro_video ? asset('storage/' . $this->intro_video) : null,
            'teacher'           => new UserResource($this->whenLoaded('teacher')),
            'lessons'           => LessonResource::collection($this->whenLoaded('lessons')),
            'lessons_count'     => $this->whenLoaded('lessons', fn() => $this->lessons->count()),
            'created_at'        => $this->created_at?->toDateString(),
        ];
    }
}
