<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lesson extends Model
{
    protected $fillable = [
        'course_id',
        'title',
        'description',
        'video_url',
        'mux_upload_id',
        'mux_asset_id',
        'mux_playback_id',
        'video_duration_seconds',
        'video_status',
        'duration',
        'order',
        'is_free',
    ];

    protected $casts = [
        'is_free'                => 'boolean',
        'order'                  => 'integer',
        'video_duration_seconds' => 'integer',
    ];

    protected $appends = ['stream_url'];

    public function getStreamUrlAttribute(): ?string
    {
        if ($this->mux_playback_id) {
            return "https://stream.mux.com/{$this->mux_playback_id}.m3u8";
        }
        return $this->video_url;
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function attachments()
    {
        return $this->hasMany(LessonAttachment::class);
    }

    public function videoProgress()
    {
        return $this->hasMany(LessonVideoProgress::class);
    }
}
