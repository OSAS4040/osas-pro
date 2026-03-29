<?php

namespace App\Enums;

enum WorkOrderItemType: string
{
    case Part    = 'part';
    case Labor   = 'labor';
    case Service = 'service';
    case Other   = 'other';
}
