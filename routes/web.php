<?php

use App\Http\Controllers\Admin\AdminAccountController;
use App\Http\Controllers\Admin\AdminProfileController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CheckInController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EventController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\PaymentMethodController;
use App\Http\Controllers\Admin\PaymentProofController as AdminPaymentProofController;
use App\Http\Controllers\Admin\PaymentVerificationController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\TicketEmailController;
use App\Http\Controllers\Auth\AdminInvitationController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CronController;
use App\Http\Controllers\Participant\EventController as ParticipantEventController;
use App\Http\Controllers\Participant\OrderController;
use App\Http\Controllers\Participant\PaymentProofController;
use App\Http\Controllers\Participant\TicketController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public participant routes
|--------------------------------------------------------------------------
|
| Open to everyone. The order status route is guarded by an unguessable
| token in the URL rather than by auth, because the MVP has no participant
| accounts.
|
*/

Route::get('/', [ParticipantEventController::class, 'index'])->name('home');

Route::get('events', [ParticipantEventController::class, 'index'])->name('events.index');

// Slug, not id: the slug is the shareable public identifier and is never
// changed once the event is created.
Route::get('events/{slug}', [ParticipantEventController::class, 'show'])->name('events.show');

Route::post('events/{slug}/orders', [OrderController::class, 'store'])
    ->name('orders.store');

Route::get('orders/{token}', [OrderController::class, 'show'])->name('orders.show');

// Proof upload. POST only, guarded by the same access token as the status
// page. Whether the order may still accept a proof is decided server-side
// under a row lock, not by this route.
Route::post('orders/{token}/payment-proof', [PaymentProofController::class, 'store'])
    ->name('orders.payment-proof.store');

// Guarded by the order's access token, not by the QR token: the QR token is
// the scanner's credential and must not travel in a browser URL.
Route::get('tickets/{token}', [TicketController::class, 'show'])->name('tickets.show');

// Same e-ticket as a downloadable PDF (Phase 13). Same credential as the page
// above, so the file never becomes a second, weaker way in.
Route::get('tickets/{token}/pdf', [TicketController::class, 'pdf'])->name('tickets.pdf');

/*
|--------------------------------------------------------------------------
| Scheduler endpoint
|--------------------------------------------------------------------------
|
| Runs the overdue-order sweep over HTTP, for hosts with no always-on process
| to run `schedule:run` (PRD FR-08). Authorised inside the controller by a
| shared secret and returns 404 when no secret is configured, so it is inert
| unless deliberately enabled.
|
| Outside the admin group on purpose: the caller is a scheduler, not a logged
| in admin, and it holds a secret rather than a session.
|
*/

Route::get('cron/expire-overdue-orders', [CronController::class, 'expireOverdueOrders'])
    ->name('cron.expire-overdue-orders');

/*
|--------------------------------------------------------------------------
| Admin authentication
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->name('login.store');

    // Invitation acceptance (Phase 11). A guest route because the invited
    // account has no password yet; the one-time token in the URL is the only
    // credential, and it is checked server-side against its stored hash.
    Route::get('invitation/{token}', [AdminInvitationController::class, 'show'])
        ->name('invitations.accept');
    Route::post('invitation/{token}', [AdminInvitationController::class, 'store'])
        ->name('invitations.accept.store');
});

Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| Admin panel
|--------------------------------------------------------------------------
|
| Every admin route must stay behind the `auth` and `admin` middleware.
| Event, order, payment, ticket, and check-in routes are added in the
| following phases inside this same group.
|
*/

Route::middleware(['auth', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        // `can:` resolves to the ability registered for the permission key in
        // AppServiceProvider. The dashboard has no model to hang a policy on,
        // so the route is the authorisation point.
        Route::get('/', [DashboardController::class, 'index'])
            ->middleware('can:dashboard.view')
            ->name('dashboard');

        // Events are never deleted: orders and tickets from later phases
        // reference them, so admins close an event instead.
        Route::resource('events', EventController::class)
            ->except(['destroy']);

        // Orders are read-only for admins. The only mutations are the two
        // payment decisions below, which are POSTs so they cannot be
        // triggered by a link, a prefetch, or a crawler.
        Route::get('orders', [AdminOrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');

        // The only way to read a payment proof: the file sits on the private
        // disk with no public URL, and this action authorises every request
        // through OrderPolicy::viewPaymentProof().
        Route::get('orders/{order}/payment-proof', [AdminPaymentProofController::class, 'show'])
            ->name('orders.payment-proof');

        Route::post('orders/{order}/approve', [PaymentVerificationController::class, 'approve'])
            ->name('orders.approve');
        Route::post('orders/{order}/reject', [PaymentVerificationController::class, 'reject'])
            ->name('orders.reject');

        // Resend the e-ticket (Phase 13). POST, so a link, a prefetch, or a
        // crawler cannot email a participant. The action reuses the existing
        // ticket and QR token; OrderPolicy::resendTicket() refuses it when there
        // is no ticket or the admin lacks `tickets.resend`.
        Route::post('orders/{order}/ticket-email', [TicketEmailController::class, 'store'])
            ->name('orders.ticket-email');

        // Check-in. The scanned token travels in the POST body, never in the
        // URL: a QR token is the ticket's only credential, and in a query
        // string it would be written to server logs and browser history.
        Route::get('check-in', [CheckInController::class, 'show'])->name('check-in.show');
        Route::post('check-in', [CheckInController::class, 'store'])->name('check-in.store');

        // Access control (Phase 10). Routes stay free of permission names: the
        // controllers authorise through RolePolicy and PermissionPolicy, so
        // the rule has one home and the 403 explains itself.
        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
        Route::get('roles/create', [RoleController::class, 'create'])->name('roles.create');
        Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
        Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
        Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');

        Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index');
        Route::get('permissions/create', [PermissionController::class, 'create'])->name('permissions.create');
        Route::post('permissions', [PermissionController::class, 'store'])->name('permissions.store');
        Route::get('permissions/{permission}/edit', [PermissionController::class, 'edit'])->name('permissions.edit');
        Route::put('permissions/{permission}', [PermissionController::class, 'update'])->name('permissions.update');
        Route::delete('permissions/{permission}', [PermissionController::class, 'destroy'])->name('permissions.destroy');

        // Self-service profile (Phase 11). Not permission-gated: every signed-in
        // admin may change their own name and password.
        Route::get('profile', [AdminProfileController::class, 'edit'])->name('profile.edit');
        Route::put('profile', [AdminProfileController::class, 'update'])->name('profile.update');
        Route::put('profile/password', [AdminProfileController::class, 'updatePassword'])->name('profile.password');

        // Admin accounts and invitations (Phase 11). POST for every state change
        // so a link, prefetch, or crawler cannot activate or revoke an account.
        Route::get('accounts', [AdminAccountController::class, 'index'])->name('accounts.index');
        Route::get('accounts/create', [AdminAccountController::class, 'create'])->name('accounts.create');
        Route::post('accounts', [AdminAccountController::class, 'store'])->name('accounts.store');
        Route::post('accounts/{user}/invitation', [AdminAccountController::class, 'resendInvitation'])
            ->name('accounts.invitation.resend');
        Route::post('accounts/{user}/activate', [AdminAccountController::class, 'activate'])
            ->name('accounts.activate');
        Route::post('accounts/{user}/deactivate', [AdminAccountController::class, 'deactivate'])
            ->name('accounts.deactivate');

        // Payment settings (Phase 12). Toggling follows the grantable
        // `payment-methods.manage` permission. Editing the account, VA, and
        // QRIS details is Super Admin only, which PaymentMethodPolicy
        // enforces rather than the routes.
        Route::get('payment-methods', [PaymentMethodController::class, 'index'])
            ->name('payment-methods.index');
        Route::post('payment-methods/{paymentMethod}/toggle', [PaymentMethodController::class, 'toggle'])
            ->name('payment-methods.toggle');
        Route::get('payment-methods/{paymentMethod}/edit', [PaymentMethodController::class, 'edit'])
            ->name('payment-methods.edit');
        Route::put('payment-methods/{paymentMethod}', [PaymentMethodController::class, 'update'])
            ->name('payment-methods.update');

        // Read-only audit trail (Phase 12). Super Admin only, enforced by
        // AuditLogPolicy. There are no write routes: the trail is append-only
        // and is written by AuditLogger inside the operation it records.
        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('audit-logs/{auditLog}', [AuditLogController::class, 'show'])->name('audit-logs.show');
    });
