<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MentorProfile extends Model
{
    protected $fillable = [
        'user_id',
        'designation',
        'short_bio',
        'specialization',
        'intro',
        'intro_video',
        'price_per_hour',
        'industry',
        'expertise_list',
        'preferred_student_level',
        'languages',
        'languages_list',
        'rating',
        'total_reviews',
        'is_verified',
        'is_featured',
        'experience_years',
        'profile_setup_complete',
    ];

    protected $casts = [
        'is_verified'            => 'boolean',
        'is_featured'            => 'boolean',
        'profile_setup_complete' => 'boolean',
        'price_per_hour'         => 'float',
        'rating'                 => 'float',
        'total_reviews'          => 'integer',
        'expertise_list'         => 'array',
        'languages_list'         => 'array',
    ];

    // Required fields to consider profile complete
    public static array $requiredFields = [
        'designation', 'short_bio', 'price_per_hour', 'languages_list', 'expertise_list',
    ];

    public function isComplete(): bool
    {
        foreach (self::$requiredFields as $field) {
            $value = $this->$field;
            if (empty($value)) {
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

    public function getIntroVideoUrlAttribute(): ?string
    {
        if (!$this->intro_video) {
            return null;
        }
        return str_starts_with($this->intro_video, 'http')
            ? $this->intro_video
            : asset('storage/' . $this->intro_video);
    }

    public function availability()
    {
        return $this->hasMany(Availability::class, 'mentor_id', 'user_id');
    }
}
