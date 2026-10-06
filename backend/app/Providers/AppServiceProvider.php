<?php

namespace App\Providers;

use App\Billing\CompoundInterest;
use App\Billing\InterestStrategy;
use App\Billing\SimpleInterest;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(InterestStrategy::class, function (): InterestStrategy {
            return match (config('billing.interest')) {
                'compound' => new CompoundInterest,
                'simple' => new SimpleInterest,
                default => throw new InvalidArgumentException('A estratégia de juros configurada é inválida.'),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
