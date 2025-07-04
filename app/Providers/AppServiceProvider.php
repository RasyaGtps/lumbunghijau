<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\CheckRole;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
        Route::aliasMiddleware('role', CheckRole::class);
        
        // Optimize Redis connections
        Redis::enableEvents(false);
        
        // Disable query log in production
        if (app()->environment('production')) {
            DB::disableQueryLog();
        }
        
        // Set default string length for MySQL older versions
        Schema::defaultStringLength(191);
    }
}
