<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->runningUnitTests()) {
            $this->guardTestingDatabase();
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }

    private function guardTestingDatabase(): void
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        if ($connection === 'sqlite' && $database === ':memory:') {
            return;
        }

        throw new RuntimeException(
            'Refusing to run tests because the configured database is not sqlite :memory:. '
            .'This protects local, staging, and production databases from RefreshDatabase or migration cleanup. '
            .'Clear cached config and run tests with DB_CONNECTION=sqlite DB_DATABASE=:memory:.'
        );
    }
}
