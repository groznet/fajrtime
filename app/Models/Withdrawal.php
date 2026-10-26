<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'amount', 'status', 'details', 'processed_at'])]
class Withdrawal extends Model
{
    public const LABELS = [
        'requested' => 'Ожидает',
        'approved' => 'Одобрена',
        'paid' => 'Выплачена',
        'rejected' => 'Отклонена',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'details' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function label(): string
    {
        return self::LABELS[$this->status] ?? $this->status;
    }
}
