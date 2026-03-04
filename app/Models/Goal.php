<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Goal extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'target_value',
        'current_value',
        'unit',
        'category',
        'deadline',
        'achieved_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'target_value'  => 'decimal:2',
            'current_value' => 'decimal:2',
            'deadline'      => 'date',
            'achieved_at'   => 'datetime',
            'is_active'     => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getProgressPercentageAttribute(): float
    {
        if ($this->target_value == 0) {
            return 0;
        }
        return min(100, round(($this->current_value / $this->target_value) * 100, 1));
    }
}
