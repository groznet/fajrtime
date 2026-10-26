<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit log row. Written together with the action it describes and never updated.
 */
#[Fillable(['admin_id', 'action', 'subject_type', 'subject_id', 'reason'])]
class AdminAction extends Model
{
    const UPDATED_AT = null;

    public const LABELS = [
        'withdrawal.paid' => 'Вывод выплачен',
        'withdrawal.rejected' => 'Вывод отклонён',
        'participant.restored' => 'Участник возвращён в челлендж',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function label(): string
    {
        return self::LABELS[$this->action] ?? $this->action;
    }

    public static function record(User $admin, string $action, Model $subject, ?string $reason = null): self
    {
        return static::create([
            'admin_id' => $admin->id,
            'action' => $action,
            'subject_type' => class_basename($subject),
            'subject_id' => $subject->getKey(),
            'reason' => $reason,
        ]);
    }
}
