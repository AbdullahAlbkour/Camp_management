<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Blade::if('role', function (...$roles): bool {
            $user = auth()->user();

            return $user !== null && $user->hasAnyRole($roles);
        });
    }
}
