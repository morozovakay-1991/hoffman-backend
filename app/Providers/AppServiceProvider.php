<?php

namespace App\Providers;

use App\Domain\Verification\Contracts\GraduateDirectoryProviderInterface;
use App\Domain\Verification\Providers\CsvGraduateDirectoryProvider;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
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

        // Brute-force protection: 5 attempts per minute per IP+email combination.
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($this->authAttemptKey($request));
        });

        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(5)->by($this->authAttemptKey($request));
        });

        // Protects the graduate directory from being enumerated via repeated submissions.
        RateLimiter::for('verification', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('profile', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        Event::listen(SocialiteWasCalled::class, AppleExtendSocialite::class);

        // Scramble's docs middleware already allows `local` unconditionally; this gate
        // extends that to `staging` while keeping `/docs/api` closed in production.
        // The nullable $user is required so Laravel evaluates this for guests too —
        // Gate::allows() refuses to call a callback with no parameters for a guest.
        Gate::define('viewApiDocs', fn (?User $user = null) => app()->environment('staging'));
    }

    private function authAttemptKey(Request $request): string
    {
        $identifier = strtolower((string) $request->input('email'));

        return $request->ip().'|'.$identifier;
    }
}
