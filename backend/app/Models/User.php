<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->role === UserRole::Administrator;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Administrator;
    }

    public function isLawyer(): bool
    {
        return $this->role === UserRole::Lawyer;
    }

    public function isManager(): bool
    {
        return $this->role === UserRole::Manager;
    }

    public function isInitiator(): bool
    {
        return $this->role === UserRole::Initiator;
    }

    public function substitutions(): HasMany
    {
        return $this->hasMany(Substitution::class);
    }

    public function substituteFor(): HasMany
    {
        return $this->hasMany(Substitution::class, 'substitute_user_id');
    }
}
