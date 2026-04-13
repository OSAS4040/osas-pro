<?php

namespace App\Enums;

enum CompanyReceivableEntryType: string
{
    case Charge = 'charge';
    case Reversal = 'reversal';
}
