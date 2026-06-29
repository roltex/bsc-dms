<?php

namespace App\Enums;

enum UserRole: string
{
    case Initiator = 'initiator';
    case Manager = 'manager';
    case Lawyer = 'lawyer';
    case Administrator = 'administrator';

    public function label(): string
    {
        return match ($this) {
            self::Initiator => 'Initiator',
            self::Manager => 'Manager',
            self::Lawyer => 'Lawyer / Super User',
            self::Administrator => 'Administrator',
        };
    }

    public function canAccessAdmin(): bool
    {
        return $this === self::Administrator;
    }
}
