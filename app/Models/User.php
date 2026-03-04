<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * Campos permitidos para mass assignment.
     * NUNCA incluir: role, is_admin, password (manipular via métodos).
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'date_of_birth',
        'avatar_url',
        'is_active',
    ];

    /**
     * Campos ocultos na serialização JSON — nunca expor senha ou tokens.
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'date_of_birth'     => 'date',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
        ];
    }

    // ==================== Relationships ====================

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latest();
    }

    public function anamnesis(): HasOne
    {
        return $this->hasOne(Anamnesis::class);
    }

    public function goals(): HasMany
    {
        return $this->hasMany(Goal::class);
    }

    public function meals(): HasMany
    {
        return $this->hasMany(Meal::class);
    }

    public function progressPhotos(): HasMany
    {
        return $this->hasMany(ProgressPhoto::class);
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'sender_id');
    }

    public function receivedMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'receiver_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    // ==================== Helpers ====================

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isStudent(): bool
    {
        return $this->hasRole('aluno');
    }

    public function hasActiveSubscription(): bool
    {
        return $this->subscription()
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->exists();
    }
}
