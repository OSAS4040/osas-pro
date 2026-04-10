<?php

namespace App\Enums;

enum ServicePricingPolicyType: string
{
    case CustomerSpecific = 'customer_specific';
    case CustomerGroup = 'customer_group';
    case Contract = 'contract';
    case General = 'general';
}
