<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseCertificate extends Model
{
    protected $fillable = [
        'enrollment_id',
        'student_id',
        'course_id',
        'certificate_number',
        'issued_at',
        'certificate_path',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
    ];

    protected $appends = ['download_url'];

    public function getDownloadUrlAttribute(): string
    {
        return url('/api/v1/student/courses/' . $this->course_id . '/certificate/download');
    }

    public function enrollment()
    {
        return $this->belongsTo(CourseEnrollment::class, 'enrollment_id');
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}
