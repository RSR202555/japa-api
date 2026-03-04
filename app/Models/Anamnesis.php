<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Anamnesis extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'weight',
        'height',
        'body_fat_percentage',
        'objective',
        'physical_activity_level',
        'health_conditions',
        'medications',
        'food_restrictions',
        'food_preferences',
        'meals_per_day',
        'water_intake_liters',
        'sleep_hours',
        'stress_level',
        'additional_notes',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'weight'               => 'decimal:2',
            'height'               => 'decimal:2',
            'body_fat_percentage'  => 'decimal:2',
            'health_conditions'    => 'array',
            'food_restrictions'    => 'array',
            'food_preferences'     => 'array',
            'completed_at'         => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
