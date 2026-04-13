<?php

namespace App\Enums;

enum WalletType: string
{
    case CustomerMain  = 'customer_main';
    case FleetMain     = 'fleet_main';
    case VehicleWallet = 'vehicle_wallet';

    public function label(): string
    {
        return match($this) {
            self::CustomerMain  => 'Individual Wallet',
            self::FleetMain     => 'Fleet Main Wallet',
            self::VehicleWallet => 'Vehicle Wallet',
        };
    }

    public function isVehicleLevel(): bool
    {
        return $this === self::VehicleWallet;
    }

    public function requiresVehicle(): bool
    {
        return $this === self::VehicleWallet;
    }
}
