<?php

namespace App\Providers;

use App\Domain\Verification\Contracts\GraduateDirectoryProviderInterface;
use App\Domain\Verification\Providers\CsvGraduateDirectoryProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Apple\AppleExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(GraduateDirectoryProviderInterface::class, CsvGraduateDirectoryProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('webhooks', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        Event::listen(SocialiteWasCalled::class, AppleExtendSocialite::class);
    }
}
