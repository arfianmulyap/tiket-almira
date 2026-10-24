<?php

namespace App\Http\Controllers;

use App\Services\OrderExpiryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * HTTP entrypoint for the scheduled expiry sweep (PRD FR-08).
 *
 * A serverless host has no long-running process, so `schedule:run` never
 * fires and the schedule in routes/console.php does nothing there. The sweep
 * therefore has to be reachable over HTTP and triggered by an external clock.
 *
 * Calling the service directly rather than shelling out through Artisan is
 * deliberate: spawning a subprocess inside a function invocation is slow and
 * not reliably supported, and OrderExpiryService is already the single
 * definition of the sweep that ExpireOverdueOrders wraps. Both entrypoints run
 * the same code.
 *
 * Quota is never at risk if this endpoint stops being called: Order's
 * holdingQuota scope stops counting an order the moment its deadline passes,
 * so an unrun sweep leaves a stale status, never an overselling.
 */
class CronController extends Controller
{
    /**
     * Expire overdue unpaid orders.
     *
     * Safe to call repeatedly and concurrently. The service only transitions
     * orders that are still PENDING_PAYMENT and already overdue, so a second
     * caller finds nothing left to do (PRD FR-08, "dapat dijalankan ulang
     * tanpa merusak data").
     */
    public function expireOverdueOrders(
        Request $request,
        OrderExpiryService $expiry,
    ): JsonResponse {
        $this->authoriseCronRequest($request);

        return response()->json([
            'expired' => $expiry->sweep(),
        ]);
    }

    /**
     * Reject anyone who cannot prove they are the configured scheduler.
     *
     * This route mutates order state, so it cannot be left open. A 404 rather
     * than a 401 is returned so the endpoint's existence is not advertised to
     * someone probing the URL.
     *
     * Two credentials are accepted because the two supported schedulers send
     * different things: Vercel Cron sends `Authorization: Bearer $CRON_SECRET`
     * automatically, while a plain external cron service can usually only
     * append a query string. Both compare against the same secret with
     * hash_equals, which is not short-circuiting and so does not leak the
     * secret's length through response timing.
     *
     * With no secret configured the route refuses every request. Failing
     * closed is the only safe default: the alternative would silently expose
     * a state-changing endpoint the moment someone forgets the variable.
     */
    private function authoriseCronRequest(Request $request): void
    {
        $secret = (string) config('cron.secret');

        if ($secret === '') {
            throw new NotFoundHttpException;
        }

        $presented = $request->bearerToken() ?? (string) $request->query('token', '');

        if (! hash_equals($secret, (string) $presented)) {
            throw new NotFoundHttpException;
        }
    }
}
