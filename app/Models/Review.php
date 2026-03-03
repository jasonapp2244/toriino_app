<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $fillable = [
        'from_user_id',
        'to_user_id',
        'session_id',
        'course_id',
        'rating',
        'comment',
    ];

    protected $casts = [
        'rating' => 'float',
    ];

    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser()
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function session()
    {
        return $this->belongsTo(MentorSession::class, 'session_id');
    }

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }
}
