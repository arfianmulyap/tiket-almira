<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCheckInRequest;
use App\Models\CheckIn;
use App\Models\Ticket;
use App\Services\CheckInService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CheckInController extends Controller
{
    public function __construct(private readonly CheckInService $checkIns) {}

    /**
     * Show the scanner and the recent gate log.
     */
    public function show(): View
    {
        $this->authorize('checkIn', Ticket::class);

        return view('admin.check-in.show', [
            'recent' => $this->recentCheckIns(),
        ]);
    }

    /**
     * Validate a scanned token and admit the participant.
     *
     * Answers JSON to the scanner and a redirect to the manual form, from
     * one code path. The decision is made by CheckInService either way, so
     * the two response shapes cannot disagree about whether someone was let
     * in: only the rendering differs.
     */
    public function store(StoreCheckInRequest $request): JsonResponse|RedirectResponse
    {
        // Already enforced by the form request; repeated so the controller
        // does not depend on that detail.
        $this->authorize('checkIn', Ticket::class);

        $outcome = $this->checkIns->attempt($request->token(), $request->user());

        if ($request->expectsJson()) {
            // A refusal answers 4xx, so a client cannot read it as success
            // by checking `response.ok` alone.
            return response()->json($outcome->toArray(), $outcome->result->httpStatus());
        }

        // Flash key matches the outcome so the shared flash partial renders
        // it with the right variant.
        $flashKey = match ($outcome->result->alertVariant()) {
            'success' => 'success',
            'warning' => 'warning',
            default => 'error',
        };

        $message = $outcome->result->label().' — '.$outcome->result->description();

        if ($outcome->isSuccessful()) {
            $message = $outcome->result->label().': '
                .$outcome->ticket->order->participant_name
                .' ('.$outcome->ticket->order->order_reference.').';
        }

        return redirect()
            ->route('admin.check-in.show')
            ->with($flashKey, $message);
    }

    /**
     * The last few admissions, newest first.
     *
     * Gives the officer immediate confirmation that scans are landing, and
     * is the fallback when a participant insists they were already admitted.
     *
     * @return Collection<int, CheckIn>
     */
    private function recentCheckIns(): Collection
    {
        return CheckIn::query()
            ->with(['ticket.order.event', 'operator'])
            ->orderByDesc('checked_in_at')
            ->limit(10)
            ->get();
    }
}
