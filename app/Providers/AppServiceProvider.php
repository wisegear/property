<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        require_once app_path('Support/MarketHelpers.php');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Member check

        Gate::define('Member', function ($user) {
            return $user->has_user_role('Member');
        });

        // Admin check

        Gate::define('Admin', function ($user) {
            return $user->has_user_role('Admin');
        });

        // Prevent tests from ever using the real database

        $db = config('database.connections.pgsql.database');

        if (app()->environment('testing') && $db === 'prop') {
            fwrite(STDERR, "Testing environment cannot use the 'prop' database.\n");
            exit(1);
        }

        // Destruction protection

        if (app()->runningInConsole()) {
            $db = config('database.connections.pgsql.database');

            if ($db === 'prop') {
                $argv = $_SERVER['argv'] ?? [];

                $blocked = [
                    'migrate:fresh',
                    'migrate:refresh',
                    'migrate:reset',
                    'db:wipe',
                    'schema:dump',
                ];

                foreach ($blocked as $command) {
                    if (in_array($command, $argv)) {
                        fwrite(STDERR, "Blocked destructive command on 'prop' database.\n");
                        exit(1);
                    }
                }
            }
        }

    }
}
