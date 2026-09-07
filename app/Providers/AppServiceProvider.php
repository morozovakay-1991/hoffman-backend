<?php

namespace App\Providers;

use App\Domain\Verification\Contracts\GraduateDirectoryProviderInterface;
use App\Domain\Verification\Providers\CsvGraduateDirectoryProvider;
use Illuminate\Support\ServiceProvider;

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
        //
    }
}
