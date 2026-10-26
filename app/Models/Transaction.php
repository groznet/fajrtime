<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Ledger row. Created only by WalletService and never updated.
 */
#[Fillable(['user_id', 'type', 'amount', 'affects', 'challenge_id', 'payment_id', 'withdrawal_id', 'description'])]
class Transaction extends Model
{
    const UPDATED_AT = null;

    public const LABELS = [
        'topup' => 'Пополнение',
        'stake_lock' => 'Ставка заблокирована',
        'stake_unlock' => 'Ставка разблокирована',
        'forfeit' => 'Ставка потеряна',
        'payout' => 'Выплата за челлендж',
        'withdrawal' => 'Вывод средств',
        'adjustment' => 'Корректировка',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Ledger rows are immutable.'));
    }

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function label(): string
    {
        return self::LABELS[$this->type] ?? $this->type;
    }
}
