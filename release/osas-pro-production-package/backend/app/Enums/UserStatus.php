<?php

namespace App\Enums;

enum UserStatus: string
{
    case Active    = 'active';
    case Inactive  = 'inactive';
    case Suspended = 'suspended';
    case Blocked   = 'blocked';

    public function label(): string
    {
        return match($this) {
            self::Active    => 'Active',
            self::Inactive  => 'Inactive',
            self::Suspended => 'Suspended',
            self::Blocked   => 'Blocked',
        };
    }

    public function canLogin(): bool
    {
        return $this === self::Active;
    }
}
