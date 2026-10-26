<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'gateway', 'gateway_payment_id', 'amount_gross', 'commission', 'amount_net', 'status', 'confirmation_url', 'paid_at'])]
class Payment extends Model
{
    protected function casts(): array
    {
        return [
            'amount_gross' => 'integer',
            'commission' => 'integer',
            'amount_net' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
