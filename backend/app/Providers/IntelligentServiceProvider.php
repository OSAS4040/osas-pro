<?php

namespace App\Providers;

use App\Services\DomainEventRecorder;
use App\Services\IntelligentEventEmitter;
use Illuminate\Support\ServiceProvider;

class IntelligentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DomainEventRecorder::class);
        $this->app->singleton(IntelligentEventEmitter::class);
    }

    public function boot(): void {}
}
