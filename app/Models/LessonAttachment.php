<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LessonAttachment extends Model
{
    protected $fillable = [
        'lesson_id',
        'type',
        'file_path',
        'original_name',
        'file_size_kb',
    ];

    protected $casts = [
        'file_size_kb' => 'integer',
    ];

    protected $appends = ['url'];

    public function getUrlAttribute(): string
    {
        return asset('storage/' . $this->file_path);
    }

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }
}
