<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LessonVideoProgress extends Model
{
    protected $table = 'lesson_video_progress';

    protected $fillable = [
        'enrollment_id',
        'lesson_id',
        'watched_seconds',
        'total_seconds',
        'percentage_watched',
        'last_position_seconds',
        'is_completed',
        'completed_at',
    ];

    protected $casts = [
        'watched_seconds'      => 'integer',
        'total_seconds'        => 'integer',
        'percentage_watched'   => 'float',
        'last_position_seconds'=> 'integer',
        'is_completed'         => 'boolean',
        'completed_at'         => 'datetime',
    ];

    public function enrollment()
    {
        return $this->belongsTo(CourseEnrollment::class, 'enrollment_id');
    }

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }
}
