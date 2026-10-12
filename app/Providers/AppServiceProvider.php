<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Set MySQL session time zone to match Laravel/PHP timezone offset (+08:00)
        try {
            DB::statement("SET time_zone = '".date('P')."'");
        } catch (\Throwable $e) {
            // ignore if DB not connected (e.g., during caches/warmups)
        }
    }
}
