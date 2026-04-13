<?php

/**
 * Mobile app V1: module visibility + default home resolution from server-side permissions.
 * UI must not be the security boundary; this only drives navigation.
 */
return [
    /**
     * Each module appears in the mobile shell if the user has ANY listed permission.
     * Owner role expands to all_permissions elsewhere — all modules evaluate true.
     */
    'modules' => [
        ['id' => 'dashboard', 'requires_any' => [
            'reports.view', 'reports.operations.view', 'reports.financial.view',
        ]],
        ['id' => 'work_orders', 'requires_any' => [
            'work_orders.view', 'work_orders.create', 'work_orders.update', 'work_orders.delete',
        ]],
        ['id' => 'vehicles', 'requires_any' => [
            'vehicles.view', 'vehicles.create', 'vehicles.update', 'vehicles.delete',
        ]],
        ['id' => 'customers', 'requires_any' => [
            'customers.view', 'customers.create', 'customers.update', 'customers.delete',
        ]],
        ['id' => 'invoices', 'requires_any' => [
            'invoices.view', 'invoices.create', 'invoices.update', 'invoices.delete',
        ]],
        ['id' => 'inventory', 'requires_any' => [
            'inventory.view', 'inventory.adjust', 'products.view',
        ]],
        ['id' => 'fleet', 'requires_any' => [
            'fleet.workorder.view', 'fleet.workorder.create', 'fleet.wallet.view',
        ]],
    ],

    /**
     * First module id in this list that is enabled becomes default home_screen.
     */
    'home_screen_priority' => [
        'work_orders',
        'fleet',
        'vehicles',
        'customers',
        'invoices',
        'inventory',
        'dashboard',
    ],

    'fallback_home_screen' => 'dashboard',
];
