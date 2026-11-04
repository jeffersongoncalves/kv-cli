<?php

namespace App\Providers;

use App\Services\ConnectionService;
use App\Services\RedisService;
use Illuminate\Support\ServiceProvider;
use JeffersonGoncalves\LaravelZero\SelfUpdate\PharUpdater;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ConnectionService::class);
        $this->app->singleton(RedisService::class);

        $this->app->singleton(PharUpdater::class, fn () => new PharUpdater(
            githubRepo: 'jeffersongoncalves/kv-cli',
            assetName: 'kv.phar',
            tempPrefix: 'kv_',
            currentVersion: (string) config('app.version', 'unreleased'),
        ));
    }
}
