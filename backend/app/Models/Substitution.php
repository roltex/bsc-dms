<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class Substitution extends Model
{
    protected $fillable = [
        'user_id',
        'substitute_user_id',
        'from_date',
        'to_date',
    ];

    protected function casts(): array
    {
        return [
            'from_date' => 'date',
            'to_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function substituteUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'substitute_user_id');
    }

    public function scopeActiveNow(Builder $query): Builder
    {
        $today = now()->toDateString();
        return $query->where('from_date', '<=', $today)->where('to_date', '>=', $today);
    }

    public static function getSubstitutesFor(int $userId): array
    {
        return static::activeNow()
            ->where('user_id', $userId)
            ->pluck('substitute_user_id')
            ->toArray();
    }

    public static function getOriginalUsersFor(int $substituteUserId): array
    {
        return static::activeNow()
            ->where('substitute_user_id', $substituteUserId)
            ->pluck('user_id')
            ->toArray();
    }

    public static function isSubstituteFor(int $substituteId, int $originalId): bool
    {
        return static::activeNow()
            ->where('user_id', $originalId)
            ->where('substitute_user_id', $substituteId)
            ->exists();
    }
}
