<?php

namespace App\Providers;

use App\Services\IdempotencyGuard;
use App\Services\NotificationDispatcher;
use App\Services\ProviderFactory;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ProviderFactory::class);
        $this->app->singleton(IdempotencyGuard::class);
        $this->app->singleton(NotificationDispatcher::class);
    }

    public function boot(): void {}
}
