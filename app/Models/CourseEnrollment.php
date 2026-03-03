<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseEnrollment extends Model
{
    protected $fillable = [
        'course_id',
        'student_id',
        'amount_paid',
        'progress_percent',
        'progress',
        'status',
        'completed_at',
    ];

    protected $casts = [
        'amount_paid'      => 'float',
        'progress_percent' => 'integer',
        'progress'         => 'array',
        'completed_at'     => 'datetime',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
