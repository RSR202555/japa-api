<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plan_id',
        'status',
        'starts_at',
        'expires_at',
        'cancelled_at',
        'infinitypay_subscription_id',
        'infinitypay_charge_id',
        'last_payment_at',
        'next_payment_at',
    ];

    /**
     * Status possíveis:
     * - pending: aguardando pagamento
     * - active: ativa e válida
     * - expired: expirada
     * - cancelled: cancelada pelo usuário ou sistema
     * - failed: falha no pagamento
     */
    protected function casts(): array
    {
        return [
            'starts_at'        => 'datetime',
            'expires_at'       => 'datetime',
            'cancelled_at'     => 'datetime',
            'last_payment_at'  => 'datetime',
            'next_payment_at'  => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->expires_at->isFuture();
    }
}
