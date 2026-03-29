<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    private array $permissions = [
        'companies' => [
            ['name' => 'companies.view',   'description' => 'View company details'],
            ['name' => 'companies.update', 'description' => 'Update company settings'],
        ],
        'branches' => [
            ['name' => 'branches.view',   'description' => 'View branches'],
            ['name' => 'branches.create', 'description' => 'Create branches'],
            ['name' => 'branches.update', 'description' => 'Update branches'],
            ['name' => 'branches.delete', 'description' => 'Delete branches'],
        ],
        'users' => [
            ['name' => 'users.view',   'description' => 'View users'],
            ['name' => 'users.create', 'description' => 'Create users'],
            ['name' => 'users.update', 'description' => 'Update users'],
            ['name' => 'users.delete', 'description' => 'Delete users'],
        ],
        'customers' => [
            ['name' => 'customers.view',   'description' => 'View customers'],
            ['name' => 'customers.create', 'description' => 'Create customers'],
            ['name' => 'customers.update', 'description' => 'Update customers'],
            ['name' => 'customers.delete', 'description' => 'Delete customers'],
        ],
        'vehicles' => [
            ['name' => 'vehicles.view',   'description' => 'View vehicles'],
            ['name' => 'vehicles.create', 'description' => 'Create vehicles'],
            ['name' => 'vehicles.update', 'description' => 'Update vehicles'],
            ['name' => 'vehicles.delete', 'description' => 'Delete vehicles'],
        ],
        'products' => [
            ['name' => 'products.view',   'description' => 'View products/services'],
            ['name' => 'products.create', 'description' => 'Create products/services'],
            ['name' => 'products.update', 'description' => 'Update products/services'],
            ['name' => 'products.delete', 'description' => 'Delete products/services'],
        ],
        'inventory' => [
            ['name' => 'inventory.view',   'description' => 'View inventory levels'],
            ['name' => 'inventory.adjust', 'description' => 'Adjust inventory quantities'],
        ],
        'invoices' => [
            ['name' => 'invoices.view',   'description' => 'View invoices'],
            ['name' => 'invoices.create', 'description' => 'Create invoices'],
            ['name' => 'invoices.update', 'description' => 'Update invoices'],
            ['name' => 'invoices.delete', 'description' => 'Delete draft invoices'],
        ],
        'work_orders' => [
            ['name' => 'work_orders.view',   'description' => 'View work orders'],
            ['name' => 'work_orders.create', 'description' => 'Create work orders'],
            ['name' => 'work_orders.update', 'description' => 'Update work orders'],
            ['name' => 'work_orders.delete', 'description' => 'Delete work orders'],
        ],
        'suppliers' => [
            ['name' => 'suppliers.view',   'description' => 'View suppliers'],
            ['name' => 'suppliers.create', 'description' => 'Create suppliers'],
            ['name' => 'suppliers.update', 'description' => 'Update suppliers'],
            ['name' => 'suppliers.delete', 'description' => 'Delete suppliers'],
        ],
        'purchases' => [
            ['name' => 'purchases.view',   'description' => 'View purchase orders'],
            ['name' => 'purchases.create', 'description' => 'Create purchase orders'],
        ],
        'reports' => [
            ['name' => 'reports.view', 'description' => 'View all reports'],
        ],
        'access_control' => [
            ['name' => 'cross_branch_access', 'description' => 'Access data across multiple branches'],
            ['name' => 'api_keys.manage',     'description' => 'Manage API keys'],
            ['name' => 'webhooks.manage',     'description' => 'Manage webhook endpoints'],
        ],
        'subscriptions' => [
            ['name' => 'subscriptions.view',   'description' => 'View subscription details'],
            ['name' => 'subscriptions.manage', 'description' => 'Manage subscription and billing'],
        ],
    ];

    private array $systemRoles = [
        'owner' => [
            'description'  => 'Full access to all features and settings',
            'permissions'  => '*',
        ],
        'manager' => [
            'description'  => 'Manage operations across the company',
            'permissions'  => [
                'branches.view', 'branches.create', 'branches.update',
                'users.view', 'users.create', 'users.update',
                'customers.view', 'customers.create', 'customers.update', 'customers.delete',
                'vehicles.view', 'vehicles.create', 'vehicles.update', 'vehicles.delete',
                'products.view', 'products.create', 'products.update',
                'inventory.view', 'inventory.adjust',
                'invoices.view', 'invoices.create', 'invoices.update',
                'work_orders.view', 'work_orders.create', 'work_orders.update',
                'suppliers.view', 'suppliers.create', 'suppliers.update',
                'purchases.view', 'purchases.create',
                'reports.view',
                'cross_branch_access',
                'subscriptions.view',
            ],
        ],
        'cashier' => [
            'description'  => 'POS and invoice operations',
            'permissions'  => [
                'customers.view', 'customers.create', 'customers.update',
                'vehicles.view',
                'products.view',
                'inventory.view',
                'invoices.view', 'invoices.create',
                'work_orders.view',
            ],
        ],
        'accountant' => [
            'description'  => 'Financial and billing access',
            'permissions'  => [
                'customers.view',
                'invoices.view', 'invoices.create', 'invoices.update',
                'reports.view',
                'purchases.view',
                'suppliers.view',
                'subscriptions.view',
            ],
        ],
        'technician' => [
            'description'  => 'Workshop and vehicle operations',
            'permissions'  => [
                'vehicles.view',
                'work_orders.view', 'work_orders.update',
                'inventory.view',
                'products.view',
            ],
        ],
        'viewer' => [
            'description'  => 'Read-only access',
            'permissions'  => [
                'customers.view',
                'vehicles.view',
                'products.view',
                'inventory.view',
                'invoices.view',
                'work_orders.view',
                'reports.view',
            ],
        ],
    ];

    public function run(): void
    {
        foreach ($this->permissions as $group => $perms) {
            foreach ($perms as $perm) {
                Permission::updateOrCreate(
                    ['name' => $perm['name'], 'guard_name' => 'sanctum'],
                    [
                        'group'       => $group,
                        'description' => $perm['description'],
                    ]
                );
            }
        }

        $this->command->info('Permissions seeded: ' . Permission::count());

        foreach ($this->systemRoles as $roleName => $roleData) {
            $role = Role::updateOrCreate(
                ['name' => $roleName, 'company_id' => null, 'guard_name' => 'sanctum'],
                [
                    'description' => $roleData['description'],
                    'is_system'   => true,
                ]
            );

            if ($roleData['permissions'] === '*') {
                $allPermIds = Permission::pluck('id');
                $role->permissions()->sync($allPermIds);
            } else {
                $permIds = Permission::whereIn('name', $roleData['permissions'])->pluck('id');
                $role->permissions()->sync($permIds);
            }

            $this->command->info("Role [{$roleName}] seeded with " . $role->permissions()->count() . ' permissions.');
        }
    }
}
