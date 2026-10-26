<?php

namespace App\Models;

use Database\Factories\ChallengeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['group_id', 'stake_amount', 'start_date', 'end_date', 'status', 'forfeit_pool', 'survivor_share'])]
class Challenge extends Model
{
    /** @use HasFactory<ChallengeFactory> */
    use HasFactory;

    /** Statuses in which the stakes of participants are locked. */
    public const OPEN = ['recruiting', 'active'];

    public const STATUS_LABELS = [
        'recruiting' => 'Набор участников',
        'active' => 'Идёт',
        'settling' => 'Подведение итогов',
        'completed' => 'Завершён',
        'cancelled' => 'Отменён',
    ];

    protected function casts(): array
    {
        return [
            'stake_amount' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'forfeit_pool' => 'integer',
            'survivor_share' => 'integer',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ChallengeParticipant::class);
    }

    public function isRecruiting(): bool
    {
        return $this->status === 'recruiting';
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }
}
