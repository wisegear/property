<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Member check

        Gate::define('Member', function ($user)
        {
            return $user->has_user_role('Member');
        });

        
        // Admin check

        Gate::define('Admin', function ($user)
        {
            return $user->has_user_role('Admin');
        });
    }
}
