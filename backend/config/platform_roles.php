<?php

declare(strict_types=1);

/**
 * platform_role (users.platform_role) → permission keys.
 * Use '*' for full platform authority.
 */
return [
    'roles' => [
        'super_admin' => ['*'],
        'platform_admin' => [
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
        'support_agent' => [
            'platform.companies.read',
            'platform.registration.read',
            'platform.registration.manage',
            'platform.announcement.read',
        ],
        'finance_admin' => [
            'platform.companies.read',
            'platform.financial_model.manage',
            'platform.subscription.manage',
            'platform.reporting.read',
            'platform.reporting.export',
        ],
        'operations_admin' => [
            'platform.ops.read',
            'platform.companies.read',
            'platform.companies.operational',
            'platform.vertical.assign',
            'platform.cancellations.read',
            'platform.cancellations.manage',
            'platform.reporting.read',
        ],
        'auditor' => [
            'platform.audit.read',
            'platform.companies.read',
            'platform.reporting.read',
        ],
    ],
];
