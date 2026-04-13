<?php

declare(strict_types=1);

/**
 * Canonical platform permission keys (IAM authority layer).
 * Role → permission mapping lives in config/platform_roles.php.
 */
return [
    'all' => [
        'platform.ops.read',
        'platform.audit.read',
        'platform.companies.read',
        'platform.companies.operational',
        'platform.subscription.manage',
        'platform.vertical.assign',
        'platform.financial_model.manage',
        'platform.cancellations.read',
        'platform.cancellations.manage',
        'platform.announcement.read',
        'platform.announcement.manage',
        'platform.registration.read',
        'platform.registration.manage',
        'platform.reporting.read',
        'platform.reporting.export',
        'platform.catalog.manage',
    ],
];
