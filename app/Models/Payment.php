<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'user_id',
        'payable_type',
        'payable_id',
        'amount',
        'currency',
        'status',
        'payment_method',
        'transaction_id',
        'payment_reference',
        'gateway_response',
        'paid_at',
    ];

    protected $casts = [
        'amount'           => 'float',
        'gateway_response' => 'array',
        'paid_at'          => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public static function generateReference(): string
    {
        return 'PAY-' . strtoupper(\Str::random(12));
    }
}
