<?php

namespace App\Providers;

use App\Enums\AdminPermission;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\PaymentMethod;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use App\Policies\AuditLogPolicy;
use App\Policies\EventPolicy;
use App\Policies\PaymentMethodPolicy;
use App\Policies\PermissionPolicy;
use App\Policies\RolePolicy;
use App\Policies\TicketPolicy;
use App\Services\WhatsApp\WhatsAppGateway;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        /*
        | Phase 14: the WhatsApp gateway is resolved from config so a real
        | provider adapter can be added without touching the reminder service.
        | The default `log` driver makes no external call.
        */
        $this->app->singleton(WhatsAppGateway::class, function (Application $app): WhatsAppGateway {
            $driver = (string) config('whatsapp.driver', 'log');
            $class = config("whatsapp.drivers.{$driver}.class");

            if (! is_string($class) || ! is_a($class, WhatsAppGateway::class, true)) {
                throw new InvalidArgumentException(
                    "Driver WhatsApp [{$driver}] tidak dikonfigurasi dengan benar."
                );
            }

            return $app->make($class);
        });
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
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);

        // Phase 12: payment settings and the audit trail. Registered here for
        // the same reason as the others — the mapping stays visible.
        Gate::policy(PaymentMethod::class, PaymentMethodPolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);

        // One ability per catalog permission, named exactly like the stored
        // key. Route middleware (`can:roles.view`), the Blade sidebar, and the
        // controllers therefore all ask the same question, and the answer is
        // read from the current role on every request (Phase 10).
        foreach (AdminPermission::cases() as $permission) {
            Gate::define(
                $permission->value,
                fn (?User $user): bool => $user?->hasPermission($permission) ?? false,
            );
        }
    }
}
