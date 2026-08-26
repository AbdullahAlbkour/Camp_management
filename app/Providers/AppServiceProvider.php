<?php

namespace App\Providers;

use App\Services\AssistantService;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
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

        // The assistant renders on every authenticated page, so its starter
        // questions are composed rather than passed down from each controller.
        // They are role-scoped: nobody is invited to type a question that the
        // assistant will only refuse.
        View::composer('partials.assistant', function ($view): void {
            $view->with(
                'assistantSuggestions',
                app(AssistantService::class)->suggestions(auth()->user()),
            );
        });
    }
}
