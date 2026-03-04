<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProgressPhoto extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'cloudinary_public_id',
        'image_url',
        'thumbnail_url',
        'angle',
        'weight_at_photo',
        'notes',
        'taken_at',
    ];

    protected function casts(): array
    {
        return [
            'weight_at_photo' => 'decimal:2',
            'taken_at'        => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
