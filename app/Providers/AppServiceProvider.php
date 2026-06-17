<?php

namespace App\Providers;

use App\Contracts\ScoringApiInterface;
use App\Services\ScoringApiClient;
use App\Services\ScoringApiFake;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // En local/testing usa el Fake para no depender del API de scoring real
        $this->app->bind(ScoringApiInterface::class, function () {
            return app()->environment(['local', 'testing'])
                ? new ScoringApiFake()
                : new ScoringApiClient();
        });
    }

    public function boot(): void {}
}
