<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeacherProfile extends Model
{
    protected $fillable = [
        'user_id',
        'designation',
        'subject',
        'subject_field',
        'short_bio',
        'degree',
        'experience_years',
        'expertise_list',
        'languages',
        'languages_list',
        'rating',
        'total_reviews',
        'is_verified',
        'intro',
        'intro_video',
        'profile_setup_complete',
    ];

    protected $casts = [
        'is_verified'            => 'boolean',
        'profile_setup_complete' => 'boolean',
        'rating'                 => 'float',
        'total_reviews'          => 'integer',
        'expertise_list'         => 'array',
        'languages_list'         => 'array',
    ];

    // Required fields to consider profile complete
    public static array $requiredFields = [
        'subject', 'short_bio', 'languages_list', 'expertise_list',
    ];

    public function isComplete(): bool
    {
        foreach (self::$requiredFields as $field) {
            if (empty($this->$field)) {
                return false;
            }
        }
        return true;
    }

    public function missingFields(): array
    {
        $missing = [];
        foreach (self::$requiredFields as $field) {
            if (empty($this->$field)) {
                $missing[] = $field;
            }
        }
        return $missing;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function courses()
    {
        return $this->hasMany(Course::class, 'teacher_id', 'user_id');
    }

    public function getIntroVideoUrlAttribute(): ?string
    {
        if (!$this->intro_video) {
            return null;
        }
        return str_starts_with($this->intro_video, 'http')
            ? $this->intro_video
            : asset('storage/' . $this->intro_video);
    }
}
