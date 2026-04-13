<?php

namespace App\Enums;

enum ProductType: string
{
    case Physical    = 'physical';
    case Service     = 'service';
    case Consumable  = 'consumable';
    case Labor       = 'labor';

    public function label(): string
    {
        return match($this) {
            self::Physical   => 'Physical Product',
            self::Service    => 'Service',
            self::Consumable => 'Consumable',
            self::Labor      => 'Labor',
        };
    }

    public function tracksInventory(): bool
    {
        return in_array($this, [self::Physical, self::Consumable]);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
