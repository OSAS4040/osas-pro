<?php

/**
 * Domain generalization (Phase 2). Tables and models are unchanged.
 *
 * Every legacy key keeps: canonical, label, plural, route_prefix (aligned with Vue/router).
 * `use_canonical_labels`: when true, UI uses derived titles from `canonical` (see App\Support\Domain).
 */
return [

    'use_canonical_labels' => env('DOMAIN_USE_CANONICAL_LABELS', false),

    'entities' => [
        'vehicle' => [
            'canonical' => 'asset',
            'label' => 'Vehicle',
            'plural' => 'Vehicles',
            'route_prefix' => 'vehicles',
        ],

        'work_order' => [
            'canonical' => 'job',
            'label' => 'Work Order',
            'plural' => 'Work Orders',
            'route_prefix' => 'work-orders',
        ],

        'customer' => [
            'canonical' => 'account',
            'label' => 'Customer',
            'plural' => 'Customers',
            'route_prefix' => 'customers',
        ],

        'invoice' => [
            'canonical' => 'invoice',
            'label' => 'Invoice',
            'plural' => 'Invoices',
            'route_prefix' => 'invoices',
        ],

        'workshop' => [
            'canonical' => 'business_unit',
            'label' => 'Workshop',
            'plural' => 'Workshops',
            'route_prefix' => 'workshop',
        ],

        'fleet' => [
            'canonical' => 'fleet',
            'label' => 'Fleet',
            'plural' => 'Fleets',
            'route_prefix' => 'fleet',
        ],
    ],

];
