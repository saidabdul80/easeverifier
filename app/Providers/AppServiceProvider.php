<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
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
        Passport::tokensCan([
            'mcp:use' => 'Use the EaseVerifier MCP connector',
        ]);

        Passport::authorizationView(function (array $parameters) {
            /** @var User $user */
            $user = $parameters['user'];

            abort_unless(
                $user->is_active && $user->isCustomer() && $user->customer?->api_enabled,
                403,
                'This account is not permitted to connect EaseVerifier to an AI client.',
            );

            return view('mcp.authorize', $parameters);
        });

        Passport::tokensExpireIn(now()->addHour());
        Passport::refreshTokensExpireIn(now()->addDays(30));
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
