<?php

namespace App\Providers;

use App\Models\Event;
use App\Models\Ticket;
use App\Policies\EventPolicy;
use App\Policies\TicketPolicy;
use Illuminate\Support\Facades\Gate;
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
        // Registered explicitly rather than relying on naming conventions so
        // the mapping is visible and cannot break if files move.
        Gate::policy(Event::class, EventPolicy::class);
        Gate::policy(Ticket::class, TicketPolicy::class);
    }
}
