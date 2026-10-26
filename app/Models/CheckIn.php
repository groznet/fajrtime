<?php

namespace App\Models;

use Database\Factories\CheckInFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'date', 'fajr_at', 'code', 'reveal_at', 'expires_at', 'confirmed_at', 'attempts', 'status'])]
class CheckIn extends Model
{
    /** @use HasFactory<CheckInFactory> */
    use HasFactory;

    // The code must never leak into serialized output (JSON responses, logs).
    protected $hidden = ['code', 'reveal_at'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'fajr_at' => 'datetime',
            'reveal_at' => 'datetime',
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
