<?php

namespace App\Enums;

enum UserRole: string
{
    case Owner         = 'owner';
    case Manager       = 'manager';
    case Cashier       = 'cashier';
    case Accountant    = 'accountant';
    case Technician    = 'technician';
    case Viewer        = 'viewer';
    case FleetContact  = 'fleet_contact';
    case FleetManager  = 'fleet_manager';
    case Customer      = 'customer';

    public function label(): string
    {
        return match($this) {
            self::Owner        => 'Owner',
            self::Manager      => 'Manager',
            self::Cashier      => 'Cashier',
            self::Accountant   => 'Accountant',
            self::Technician   => 'Technician',
            self::Viewer       => 'Viewer',
            self::FleetContact => 'Fleet Contact',
            self::FleetManager => 'Fleet Manager',
            self::Customer     => 'Customer',
        };
    }

    public function isAdmin(): bool
    {
        return in_array($this, [self::Owner, self::Manager]);
    }

    public function isFleetSide(): bool
    {
        return in_array($this, [self::FleetContact, self::FleetManager]);
    }

    public function isCustomer(): bool
    {
        return $this === self::Customer;
    }

    public function isWorkshopSide(): bool
    {
        return !$this->isFleetSide() && !$this->isCustomer();
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
