<?php

declare(strict_types=1);

/**
 * One-off: full HTTP dispatch to GET /api/v1/internal/intelligence/overview.
 * If DB has no owner user, creates minimal tenant (company, branch, subscription, owner) + one sample domain_event.
 *
 * Usage:
 *   docker exec -e INTELLIGENT_INTERNAL_DASHBOARD_ENABLED=true -e INTELLIGENT_READ_MODELS_ENABLED=true ... saas_app sh -c "cd /var/www && php artisan config:clear && php scripts/phase2_overview_live.php"
 */

use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\DomainEvent;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require __DIR__.'/../vendor/autoload.php';

/** @var \Illuminate\Foundation\Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$setupLog = [];

$ensureTenantAndOwner = static function () use (&$setupLog): User {
    $user = User::query()
        ->where('role', UserRole::Owner)
        ->where('is_active', true)
        ->orderBy('id')
        ->first();

    if ($user !== null) {
        $setupLog[] = 'Using existing owner user id='.$user->id;

        return $user;
    }

    $company = Company::create([
        'uuid'      => (string) Str::uuid(),
        'name'      => 'Phase2 Live Demo Co',
        'currency'  => 'SAR',
        'timezone'  => 'Asia/Riyadh',
        'status'    => 'active',
        'is_active' => true,
    ]);

    $branch = Branch::create([
        'uuid'       => (string) Str::uuid(),
        'company_id' => $company->id,
        'name'       => 'Main',
        'code'       => 'MAIN',
        'status'     => 'active',
        'is_main'    => true,
        'is_active'  => true,
    ]);

    Subscription::create([
        'uuid'         => (string) Str::uuid(),
        'company_id'   => $company->id,
        'plan'         => 'professional',
        'status'       => SubscriptionStatus::Active,
        'starts_at'    => now()->subDay(),
        'ends_at'      => now()->addYear(),
        'max_branches' => 5,
        'max_users'    => 20,
    ]);

    $user = User::create([
        'uuid'       => (string) Str::uuid(),
        'company_id' => $company->id,
        'branch_id'  => $branch->id,
        'name'       => 'Phase2 Owner',
        'email'      => 'phase2_owner_'.Str::lower(Str::random(8)).'@demo.local',
        'password'   => bcrypt('Password123!'),
        'role'       => UserRole::Owner,
        'status'     => 'active',
        'is_active'  => true,
    ]);

    $setupLog[] = 'Created demo tenant + owner user id='.$user->id;

    return $user;
};

$user = $ensureTenantAndOwner();

if (DomainEvent::query()->where('company_id', $user->company_id)->doesntExist()) {
    DomainEvent::create([
        'uuid'              => (string) Str::uuid(),
        'company_id'        => $user->company_id,
        'branch_id'         => $user->branch_id,
        'aggregate_type'    => 'Customer',
        'aggregate_id'      => 'demo-1',
        'event_name'        => 'CustomerCreated',
        'event_version'     => 1,
        'payload_json'      => ['source' => 'phase2_overview_live.php'],
        'metadata_json'     => [],
        'trace_id'          => 'phase2-live',
        'correlation_id'    => 'phase2-live',
        'occurred_at'       => now()->subHour(),
        'processing_status' => 'recorded',
    ]);
    $setupLog[] = 'Inserted one sample domain_events row for company_id='.$user->company_id;
}

$token = $user->createToken('phase2-live-demo')->plainTextToken;

$snapshot = static fn (): array => [
    'domain_events'           => DomainEvent::query()->count(),
    'invoices'                => Invoice::query()->count(),
    'wallet_transactions'     => WalletTransaction::query()->count(),
    'personal_access_tokens'  => DB::table('personal_access_tokens')->count(),
];

$before = $snapshot();

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create(
    '/api/v1/internal/intelligence/overview',
    'GET',
    [],
    [],
    [],
    [
        'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        'HTTP_ACCEPT'        => 'application/json',
    ]
);

$response = $kernel->handle($request);
$kernel->terminate($request, $response);

$after = $snapshot();

$out = [
    'setup_log'                    => $setupLog,
    'http_status'                  => $response->getStatusCode(),
    'response_json'                => json_decode($response->getContent(), true),
    'row_counts_before_get'        => $before,
    'row_counts_after_get'         => $after,
    'delta_during_get'             => [
        'domain_events'          => $after['domain_events'] - $before['domain_events'],
        'invoices'               => $after['invoices'] - $before['invoices'],
        'wallet_transactions'    => $after['wallet_transactions'] - $before['wallet_transactions'],
        'personal_access_tokens' => $after['personal_access_tokens'] - $before['personal_access_tokens'],
    ],
];

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
