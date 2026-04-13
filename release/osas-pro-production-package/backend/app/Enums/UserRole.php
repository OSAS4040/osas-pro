<?php

namespace App\Enums;

enum UserRole: string
{
    case Owner         = 'owner';
    case Manager       = 'manager';
    case Staff         = 'staff';
    case Cashier       = 'cashier';
    case Accountant    = 'accountant';
    case Technician    = 'technician';
    case Viewer        = 'viewer';
    case FleetContact  = 'fleet_contact';
    case FleetManager  = 'fleet_manager';
    case Customer      = 'customer';

    /** تسجيل عبر الجوال فقط — بدون مستأجر حتى اكتمال المراجعة */
    case PhoneOnboarding = 'phone_onboarding';

    public function label(): string
    {
        return match($this) {
            self::Owner        => 'Owner',
            self::Manager      => 'Manager',
            self::Staff        => 'Staff',
            self::Cashier      => 'Cashier',
            self::Accountant   => 'Accountant',
            self::Technician   => 'Technician',
            self::Viewer       => 'Viewer',
            self::FleetContact => 'Fleet Contact',
            self::FleetManager => 'Fleet Manager',
            self::Customer     => 'Customer',
            self::PhoneOnboarding => 'Phone onboarding',
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
        return ! $this->isFleetSide() && ! $this->isCustomer() && $this !== self::PhoneOnboarding;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
