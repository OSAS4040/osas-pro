<?php

namespace App\Providers;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WalletTopUpRequest;
use App\Policies\BranchPolicy;
use App\Policies\CompanyPolicy;
use App\Policies\SubscriptionPolicy;
use App\Policies\UserPolicy;
use App\Policies\WalletTopUpRequestPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Company::class      => CompanyPolicy::class,
        Branch::class       => BranchPolicy::class,
        User::class         => UserPolicy::class,
        Subscription::class => SubscriptionPolicy::class,
        WalletTopUpRequest::class => WalletTopUpRequestPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
