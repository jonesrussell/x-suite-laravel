<?php

declare(strict_types=1);

namespace JonesRussell\XSuite;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use JonesRussell\XSuite\Console\Commands\DiscoverXContent;
use JonesRussell\XSuite\Console\Commands\MonitorXTrends;
use JonesRussell\XSuite\Console\Commands\ProcessXAutoReplies;
use JonesRussell\XSuite\Console\Commands\SyncXAnalytics;
use JonesRussell\XSuite\Models\XPost;
use JonesRussell\XSuite\Policies\XPostPolicy;
use JonesRussell\XSuite\Services\XApiService;

class XSuiteServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/x-suite.php', 'x-suite');

        $this->app->singleton(XApiService::class);
    }

    public function boot(): void
    {
        $this->registerPublishing();
        $this->registerRoutes();
        $this->registerCommands();
        $this->registerPolicies();
        $this->registerMigrations();
        $this->registerSchedule();
    }

    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/x-suite.php' => config_path('x-suite.php'),
            ], 'x-suite-config');

            $this->publishes([
                __DIR__.'/../resources/js/types' => resource_path('js/types/x-suite'),
                __DIR__.'/../resources/js/components' => resource_path('js/components/x-suite'),
                __DIR__.'/../resources/js/pages/Admin' => resource_path('js/pages/Admin'),
            ], 'x-suite-frontend');
        }
    }

    protected function registerRoutes(): void
    {
        if (config('x-suite.features.routes', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }
    }

    protected function registerCommands(): void
    {
        if (config('x-suite.features.commands', true)) {
            $this->commands([
                SyncXAnalytics::class,
                MonitorXTrends::class,
                ProcessXAutoReplies::class,
                DiscoverXContent::class,
            ]);
        }
    }

    protected function registerPolicies(): void
    {
        Gate::policy(XPost::class, XPostPolicy::class);
    }

    protected function registerMigrations(): void
    {
        if ($this->app->runningInConsole()) {
            $migrationPath = __DIR__.'/../database/migrations';

            $this->publishesMigrations([
                $migrationPath => database_path('migrations'),
            ], 'x-suite-migrations');
        }
    }

    protected function registerSchedule(): void
    {
        if (! config('x-suite.features.scheduler', false)) {
            return;
        }

        $this->app->booted(function () {
            /** @var Schedule $schedule */
            $schedule = $this->app->make(Schedule::class);

            $schedule->command('x-suite:sync-analytics')->everyFifteenMinutes();
            $schedule->command('x-suite:monitor-trends')->everyThirtyMinutes();
            $schedule->command('x-suite:process-auto-replies')->everyTenMinutes();
            $schedule->command('x-suite:discover-content')->dailyAt('02:00');
        });
    }
}
