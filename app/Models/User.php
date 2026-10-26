<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'city_id', 'timezone', 'terms_accepted_at', 'personal_data_consent_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'personal_data_consent_at' => 'datetime',
            'password' => 'hashed',
            'balance_available' => 'integer',
            'balance_locked' => 'integer',
        ];
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class);
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_members')->withPivot('joined_at');
    }

    public function participations(): HasMany
    {
        return $this->hasMany(ChallengeParticipant::class);
    }

    /**
     * Whether the user has a stake in a challenge that is still recruiting or running.
     */
    public function hasOpenChallenges(): bool
    {
        return $this->participations()
            ->where('status', 'active')
            ->whereHas('challenge', fn ($q) => $q->whereIn('status', Challenge::OPEN))
            ->exists();
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Set the city and copy its timezone (the user never picks a timezone directly).
     */
    public function moveToCity(City $city): void
    {
        $this->city_id = $city->id;
        $this->timezone = $city->timezone;
    }
}
