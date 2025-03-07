<?php

namespace Rapidez\LaravelMultiCache;

use Illuminate\Cache\CacheManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;

class MultiStoreServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        Cache::extend('multi', function ($app, $config) {
            return Cache::repository(
                new MultiStore(
                    $app,
                    $config,
                    $app->make(CacheManager::class)
                )
                // $config TODO: add this second parameter when dropping L10 support
            );
        });
    }
}
