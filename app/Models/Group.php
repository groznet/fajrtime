<?php

namespace App\Models;

use Database\Factories\GroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['creator_id', 'name', 'slug', 'description', 'stake_amount', 'cycle_days', 'visibility', 'invite_token'])]
class Group extends Model
{
    /** @use HasFactory<GroupFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'stake_amount' => 'integer',
            'cycle_days' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_members')->withPivot('joined_at');
    }

    public function challenges(): HasMany
    {
        return $this->hasMany(Challenge::class);
    }

    /**
     * The recruiting or active challenge, if any. A group runs one challenge at a time.
     */
    public function openChallenge(): HasOne
    {
        return $this->hasOne(Challenge::class)->whereIn('status', Challenge::OPEN)->latestOfMany();
    }

    public function isPublic(): bool
    {
        return $this->visibility === 'public';
    }

    public function hasMember(?User $user): bool
    {
        return $user !== null && $this->members()->whereKey($user->id)->exists();
    }

    /**
     * Public groups are open to everyone; private ones to members and to holders of the invite token.
     */
    public function isViewableBy(?User $user, ?string $inviteToken = null): bool
    {
        return $this->isPublic()
            || ($inviteToken !== null && hash_equals($this->invite_token, $inviteToken))
            || $this->hasMember($user);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
