<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['challenge_id', 'user_id', 'status', 'eliminated_on', 'payout_amount'])]
class ChallengeParticipant extends Model
{
    public const STATUS_LABELS = [
        'active' => 'В игре',
        'eliminated' => 'Выбыл',
        'survived' => 'Дошёл до конца',
        'left' => 'Вышел',
    ];

    protected function casts(): array
    {
        return [
            'eliminated_on' => 'date',
            'payout_amount' => 'integer',
        ];
    }

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }
}
