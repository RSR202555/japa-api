<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'subscription_id',
        'infinitypay_transaction_id',
        'infinitypay_charge_id',
        'amount',
        'status',
        'payment_method',
        'paid_at',
        'metadata',
        'raw_payload',
    ];

    /**
     * IMPORTANTE: raw_payload nunca é retornado na serialização padrão.
     * É armazenado apenas para auditoria/disputas.
     */
    protected $hidden = ['raw_payload'];

    protected function casts(): array
    {
        return [
            'amount'   => 'decimal:2',
            'paid_at'  => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
