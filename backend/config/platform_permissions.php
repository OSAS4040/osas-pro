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
        'platform.tenant_nav.manage',
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
        'platform.support.read',
        'platform.support.manage',
        'platform.notifications.read',
        'platform.intelligence.signals.read',
        'platform.intelligence.candidates.read',
        'platform.intelligence.incidents.read',
        'platform.intelligence.incidents.materialize',
        'platform.intelligence.decisions.read',
        'platform.intelligence.incidents.acknowledge',
        'platform.intelligence.incidents.assign_owner',
        'platform.intelligence.incidents.escalate',
        'platform.intelligence.incidents.resolve',
        'platform.intelligence.incidents.close',
        'platform.intelligence.decisions.write',
        'platform.intelligence.guided_workflows.execute',
        'platform.intelligence.controlled_actions.view',
        'platform.intelligence.controlled_actions.create_follow_up',
        'platform.intelligence.controlled_actions.request_human_review',
        'platform.intelligence.controlled_actions.link_task_reference',
        'platform.intelligence.controlled_actions.assign_owner',
        'platform.intelligence.controlled_actions.schedule',
        'platform.intelligence.controlled_actions.complete',
        'platform.intelligence.controlled_actions.cancel',
        'platform.intelligence.controlled_actions.reopen',
        /** تحكم تجاري وتسعير منصة — يجب أن تبقى متزامنة مع صلاحيات الأدوار في platform_roles.php */
        'platform.pricing.view',
        'platform.pricing.create',
        'platform.pricing.review',
        'platform.pricing.approve',
        'platform.providers.manage',
    ],
];
