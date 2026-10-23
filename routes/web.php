<?php

use App\Http\Controllers\Admin\CheckInController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EventController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\PaymentProofController as AdminPaymentProofController;
use App\Http\Controllers\Admin\PaymentVerificationController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
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

/*
|--------------------------------------------------------------------------
| Admin authentication
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
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
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

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

        // Check-in. The scanned token travels in the POST body, never in the
        // URL: a QR token is the ticket's only credential, and in a query
        // string it would be written to server logs and browser history.
        Route::get('check-in', [CheckInController::class, 'show'])->name('check-in.show');
        Route::post('check-in', [CheckInController::class, 'store'])->name('check-in.store');
    });
