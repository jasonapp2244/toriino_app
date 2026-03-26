<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Availability extends Model
{
    protected $fillable = [
        'mentor_id',
        'day',
        'start_time',
        'end_time',
        'is_available',
        'min_students',
        'max_students',
    ];

    protected $casts = [
        'is_available' => 'boolean',
        'min_students' => 'integer',
        'max_students' => 'integer',
    ];

    public function mentor()
    {
        return $this->belongsTo(User::class, 'mentor_id');
    }
}
