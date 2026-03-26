<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, SoftDeletes;

    protected $fillable = [
        'name',
        'full_name',
        'email',
        'phone',
        'role',
        'pending_role',
        'profile',
        'password',
        'otp_code',
        'otp_expires_at',
        'is_verified',
        'status',
        'two_factor_enabled',
        'email_verified_at',
        'provider',
        'provider_id',
        'timezone',
        'language',
        'fcm_token',
        'device_id',
        'device_type',
        'last_active_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'otp_code',
        'otp_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'  => 'datetime',
            'otp_expires_at'     => 'datetime',
            'last_active_at'     => 'datetime',
            'password'           => 'hashed',
            'is_verified'        => 'boolean',
            'two_factor_enabled' => 'boolean',
        ];
    }

    // ─── Profiles ────────────────────────────────────────────────
    public function profile()
    {
        return $this->hasOne(UserProfile::class);
    }

    public function mentorProfile()
    {
        return $this->hasOne(MentorProfile::class);
    }

    public function teacherProfile()
    {
        return $this->hasOne(TeacherProfile::class);
    }

    public function studentProfile()
    {
        return $this->hasOne(StudentProfile::class);
    }

    // ─── Sessions ────────────────────────────────────────────────
    public function mentorSessions()
    {
        return $this->hasMany(MentorSession::class, 'mentor_id');
    }

    public function sessionBookings()
    {
        return $this->hasMany(SessionBooking::class, 'student_id');
    }

    public function conversationsAsOne()
    {
        return $this->hasMany(Conversation::class, 'participant_one_id');
    }

    public function conversationsAsTwo()
    {
        return $this->hasMany(Conversation::class, 'participant_two_id');
    }

    public function sentMessages()
    {
        return $this->hasMany(ConversationMessage::class, 'sender_id');
    }

    public function availabilities()
    {
        return $this->hasMany(Availability::class, 'mentor_id');
    }

    // ─── Courses ─────────────────────────────────────────────────
    public function courses()
    {
        return $this->hasMany(Course::class, 'teacher_id');
    }

    public function enrolledCourses()
    {
        return $this->hasMany(CourseEnrollment::class, 'student_id');
    }

    // ─── Earnings & Finance ──────────────────────────────────────
    public function earnings()
    {
        return $this->hasMany(Earning::class);
    }

    public function withdrawals()
    {
        return $this->hasMany(Withdrawal::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription()
    {
        return $this->hasOne(Subscription::class)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->latest();
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    // ─── Social ──────────────────────────────────────────────────
    public function appNotifications()
    {
        return $this->hasMany(AppNotification::class);
    }

    public function supportTickets()
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function aiChats()
    {
        return $this->hasMany(AiChat::class);
    }

    public function reviewsGiven()
    {
        return $this->hasMany(Review::class, 'from_user_id');
    }

    public function reviewsReceived()
    {
        return $this->hasMany(Review::class, 'to_user_id');
    }

    // ─── Business Rule Helpers ───────────────────────────────────

    public function hasActiveSubscription(): bool
    {
        return $this->activeSubscription()->exists();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isBanned(): bool
    {
        return $this->status === 'banned';
    }

    public function isMentor(): bool
    {
        return $this->role === 'mentor' || $this->hasRole('mentor');
    }

    public function isTeacher(): bool
    {
        return $this->role === 'teacher' || $this->hasRole('teacher');
    }

    public function isStudent(): bool
    {
        return $this->role === 'student' || $this->hasRole('student');
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->full_name ?? $this->name ?? 'Unknown';
    }

    public function getPhotoUrlAttribute(): ?string
    {
        if (!$this->profile) {
            return null;
        }
        if (str_starts_with($this->profile, 'http')) {
            return $this->profile;
        }
        return asset('storage/' . $this->profile);
    }

    public function hasPendingSwitch(): bool
    {
        return !is_null($this->pending_role);
    }

    // OTP helpers
    public function generateOtp(): string
    {
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $this->update([
            'otp_code'       => $otp,
            'otp_expires_at' => now()->addMinutes(10),
        ]);
        return $otp;
    }

    public function isOtpValid(string $otp): bool
    {
        return $this->otp_code === $otp
            && $this->otp_expires_at
            && $this->otp_expires_at->isFuture();
    }

    public function clearOtp(): void
    {
        $this->update(['otp_code' => null, 'otp_expires_at' => null]);
    }
}
