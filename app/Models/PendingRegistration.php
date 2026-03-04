<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PendingRegistration extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'plan_id',
        'infinitypay_charge_id',
        'payment_url',
        'activation_token',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isActivationExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}
