<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MentorSession extends Model
{
    protected $table = 'mentor_sessions';

    // Max students allowed in a group session
    public const GROUP_MAX_SEATS = 5;

    protected $fillable = [
        'mentor_id',
        'title',
        'type',
        'start_time',
        'end_time',
        'duration_minutes',
        'max_seats',
        'group_max_seats',
        'seats_booked',
        'language',
        'price',
        'status',
        'join_key',
    ];

    protected $casts = [
        'start_time'       => 'datetime',
        'end_time'         => 'datetime',
        'price'            => 'float',
        'duration_minutes' => 'integer',
        'max_seats'        => 'integer',
        'group_max_seats'  => 'integer',
        'seats_booked'     => 'integer',
    ];

    public function mentor()
    {
        return $this->belongsTo(User::class, 'mentor_id');
    }

    public function bookings()
    {
        return $this->hasMany(SessionBooking::class, 'session_id');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class, 'session_id');
    }

    public function seatsAvailable(): int
    {
        return $this->max_seats - $this->seats_booked;
    }
}
