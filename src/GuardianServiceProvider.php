<?php

declare(strict_types=1);

namespace Masq\Guardian;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Laravel\Octane\Events\RequestReceived;
use Masq\Guardian\Engine\ScoringEngine;
use Masq\Guardian\Enums\TrustState;
use Masq\Guardian\Http\Middleware\EnforceTrust;
use Masq\Guardian\Registry\TrackManager;
use Masq\Guardian\Support\States;
use Masq\Guardian\Support\TrustCache;

final class GuardianServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/guardian.php', 'guardian');

        $this->app->singleton(States::class, fn ($app): States => new States(
            (string) $app['config']->get('guardian.state_enum', TrustState::class),
        ));

        $this->app->singleton(TrustCache::class, function ($app): TrustCache {
            $cacheConfig = (array) $app['config']->get('guardian.cache', []);
            $store = $cacheConfig['store'] ?? null;

            return new TrustCache(
                $app->make(CacheFactory::class)->store($store),
                (int) ($cacheConfig['ttl'] ?? 86400),
                (string) ($cacheConfig['prefix'] ?? 'guardian'),
                $app->make(States::class)->baseKey(),
            );
        });

        $this->app->singleton(TrackManager::class, fn ($app): TrackManager => new TrackManager(
            $app,
            (array) $app['config']->get('guardian', []),
        ));

        $this->app->singleton(ScoringEngine::class, fn ($app): ScoringEngine => new ScoringEngine(
            $app,
            $app->make(TrackManager::class),
            $app->make(TrustCache::class),
            $app->make(States::class),
        ));

        $this->app->singleton(Guardian::class, fn ($app): Guardian => new Guardian(
            $app,
            $app->make(ScoringEngine::class),
            $app->make(TrackManager::class),
        ));

        $this->app->alias(Guardian::class, 'guardian');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/guardian.php' => $this->app->configPath('guardian.php'),
            ], 'guardian-config');

            $this->publishesMigrations([
                __DIR__.'/../database/migrations' => $this->app->databasePath('migrations'),
            ], 'guardian-migrations');
        }

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->app->make(Router::class)->aliasMiddleware('guardian', EnforceTrust::class);

        $this->resetRequestStateOnOctane();
    }

    private function resetRequestStateOnOctane(): void
    {
        if (! class_exists(RequestReceived::class)) {
            return;
        }

        $this->app->make(Dispatcher::class)->listen(RequestReceived::class, function (): void {
            if ($this->app->resolved(Guardian::class)) {
                $this->app->make(Guardian::class)->flushRequestState();
            }

            if ($this->app->resolved(TrackManager::class)) {
                $this->app->make(TrackManager::class)->flush();
            }
        });
    }
}
