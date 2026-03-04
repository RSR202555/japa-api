<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Meal extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'meal_time',
        'foods',
        'total_calories',
        'total_protein',
        'total_carbs',
        'total_fat',
        'notes',
        'logged_at',
    ];

    protected function casts(): array
    {
        return [
            'foods'          => 'array',
            'total_calories' => 'decimal:2',
            'total_protein'  => 'decimal:2',
            'total_carbs'    => 'decimal:2',
            'total_fat'      => 'decimal:2',
            'logged_at'      => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
