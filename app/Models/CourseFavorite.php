<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseFavorite extends Model
{
    protected $fillable = [
        'student_id',
        'course_id',
    ];

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}
