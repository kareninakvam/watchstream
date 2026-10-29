<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Horizon\Horizon;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::define('access-admin', fn (User $user): bool => $user->is_admin);
        Gate::define('viewHorizon', fn (User $user): bool => $user->is_admin);
        Gate::define('viewPulse', fn (User $user): bool => $user->is_admin);

        Horizon::auth(fn (Request $request): bool => Gate::forUser($request->user())->allows('viewHorizon'));
    }
}
