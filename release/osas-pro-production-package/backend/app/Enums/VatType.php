<?php

namespace App\Enums;

enum VatType: string
{
    case Standard  = 'standard';   // 15%
    case ZeroRated = 'zero_rated'; // 0%
    case Exempt    = 'exempt';     // no VAT
    case OutOfScope = 'out_of_scope';

    public function rate(): float
    {
        return match($this) {
            self::Standard  => 15.0,
            self::ZeroRated => 0.0,
            self::Exempt    => 0.0,
            self::OutOfScope => 0.0,
        };
    }

    public function label(): string
    {
        return match($this) {
            self::Standard   => 'ضريبة قيمة مضافة 15%',
            self::ZeroRated  => 'صفري 0%',
            self::Exempt     => 'معفي',
            self::OutOfScope => 'خارج النطاق',
        };
    }
}
