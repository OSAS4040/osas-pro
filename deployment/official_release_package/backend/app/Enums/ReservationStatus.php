<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case Pending   = 'pending';
    case Consumed  = 'consumed';
    case Released  = 'released';
    case Canceled  = 'canceled';
    case Expired   = 'expired';
}
