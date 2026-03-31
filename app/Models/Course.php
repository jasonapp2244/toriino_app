<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    protected $fillable = [
        'teacher_id',
        'title',
        'description',
        'category_id',
        'level_id',
        'language',
        'duration',
        'price',
        'platform_fee',
        'thumbnail',
        'intro_video',
        'rating',
        'total_enrollments',
        'status',
        'is_live',
        'tags',
    ];

    protected $casts = [
        'price'             => 'float',
        'platform_fee'      => 'float',
        'rating'            => 'float',
        'total_enrollments' => 'integer',
        'is_live'           => 'boolean',
        'tags'              => 'array',
    ];

    public function category()
    {
        return $this->belongsTo(CourseCategory::class, 'category_id');
    }

    public function level()
    {
        return $this->belongsTo(CourseLevel::class, 'level_id');
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function lessons()
    {
        return $this->hasMany(Lesson::class)->orderBy('order');
    }

    public function enrollments()
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class, 'course_id');
    }

    public function isEnrolledBy(int $userId): bool
    {
        return $this->enrollments()->where('student_id', $userId)->exists();
    }
}
