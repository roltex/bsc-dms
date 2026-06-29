<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Partner extends Model
{
    protected $fillable = [
        'name',
        'bin_iin',
        'bank_details',
        'email',
        'reliability_data',
        'blacklisted_at',
        'blacklist_reason',
        'blacklisted_by',
    ];

    protected function casts(): array
    {
        return [
            'reliability_data' => 'array',
            'blacklisted_at' => 'datetime',
        ];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(PartnerDocument::class);
    }

    public function blacklistedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blacklisted_by');
    }

    public function isBlacklisted(): bool
    {
        return $this->blacklisted_at !== null;
    }
}
