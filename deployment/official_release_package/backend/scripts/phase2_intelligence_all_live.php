<?php

declare(strict_types=1);

/**
 * Live HTTP dispatch: overview + insights + recommendations + alerts.
 * Per-request row-count deltas (read-only check). Reuses tenant bootstrap from overview script logic.
 *
 * docker exec -e INTELLIGENT_INTERNAL_DASHBOARD_ENABLED=true -e INTELLIGENT_READ_MODELS_ENABLED=true \
 *   -e INTELLIGENT_INSIGHTS_ENABLED=true -e INTELLIGENT_RECOMMENDATIONS_ENABLED=true \
 *   -e INTELLIGENT_ALERTS_ENABLED=true -e INTELLIGENT_OVERVIEW_API_ENABLED=true \
 *   saas_app sh -c "cd /var/www && php artisan config:clear && php scripts/phase2_intelligence_all_live.php"
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
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
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
        'payload_json'      => ['source' => 'phase2_intelligence_all_live.php'],
        'metadata_json'     => [],
        'trace_id'          => 'phase2-live',
        'correlation_id'    => 'phase2-live',
        'occurred_at'       => now()->subHour(),
        'processing_status' => 'recorded',
    ]);
    $setupLog[] = 'Inserted sample domain_events row for company_id='.$user->company_id;
}

$token = $user->createToken('phase2-all-endpoints')->plainTextToken;

$snapshot = static fn (): array => [
    'domain_events'          => DomainEvent::query()->count(),
    'invoices'               => Invoice::query()->count(),
    'wallet_transactions'    => WalletTransaction::query()->count(),
    'personal_access_tokens' => DB::table('personal_access_tokens')->count(),
];

$delta = static function (array $before, array $after): array {
    $out = [];
    foreach (array_keys($before) as $k) {
        $out[$k] = $after[$k] - $before[$k];
    }

    return $out;
};

$paths = [
    'overview'        => '/api/v1/internal/intelligence/overview',
    'insights'        => '/api/v1/internal/intelligence/insights',
    'recommendations' => '/api/v1/internal/intelligence/recommendations',
    'alerts'          => '/api/v1/internal/intelligence/alerts',
];

$kernel = $app->make(Kernel::class);
$calls = [];

foreach ($paths as $name => $path) {
    $before = $snapshot();
    $request = Request::create(
        $path,
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

    $calls[$name] = [
        'path'           => $path,
        'http_status'    => $response->getStatusCode(),
        'response_json'  => json_decode($response->getContent(), true),
        'counts_before'  => $before,
        'counts_after'   => $after,
        'delta'          => $delta($before, $after),
    ];
}

$out = [
    'setup_log' => $setupLog,
    'calls'     => $calls,
];

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
