<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Enums\PaymentMethodType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePaymentMethodRequest;
use App\Http\Requests\Admin\UpdatePaymentMethodRequest;
use App\Models\Event;
use App\Models\EventPaymentMethod;
use App\Models\PaymentMethod as PaymentMethodRecord;
use App\Services\AuditLogger;
use App\Services\PaymentMethodImageService;
use App\Services\PaymentMethodRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Payment method management (Phase 12, extended in Phase 12B).
 *
 * Two scopes are managed here and they must not be confused:
 *
 *  - The global catalog: the method's name, type, recipient details, and
 *    whether it exists for the business at all. Shared by every event.
 *  - Per-event activation: whether one event offers one method. Stored as an
 *    override row so switching a method off for Event A cannot affect Event B.
 *
 * Authorisation lives in PaymentMethodPolicy rather than in the routes: reading,
 * creating, editing details, toggling, and deleting are separate permissions,
 * and publishing unconfirmed details as official stays Super Admin only.
 *
 * Nothing here rewrites an existing order. Each order carries the instructions
 * that were shown when it was created.
 */
class PaymentMethodController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PaymentMethodRegistry $registry,
        private readonly PaymentMethodImageService $images,
    ) {}

    /**
     * The catalog, optionally scoped to one event.
     *
     * With `?event=<id>` the same table gains a column showing whether that
     * event offers each method, so "what does this event accept?" is answerable
     * without opening a second screen. Without it the page is the global
     * catalog, exactly as in Phase 12.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', PaymentMethodRecord::class);

        $methods = PaymentMethodRecord::query()
            ->with('updatedBy')
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();

        // An unknown or missing id simply leaves the page unscoped; refusing it
        // would turn a stale bookmark into an error page.
        $eventId = $request->integer('event');
        $selectedEvent = $eventId > 0 ? Event::query()->find($eventId) : null;

        return view('admin.payment-methods.index', [
            'methods' => $methods,
            'events' => Event::query()->orderBy('name')->get(['id', 'name']),
            'selectedEvent' => $selectedEvent,
            // null = the event follows the global flag for that method. The
            // distinction is shown in the UI, because "off for this event" and
            // "off because it is off everywhere" need different fixes.
            'eventOverrides' => $methods
                ->mapWithKeys(fn (PaymentMethodRecord $method) => [
                    $method->getKey() => $this->registry->overrideFor($selectedEvent, $method),
                ])
                ->all(),
        ]);
    }

    /**
     * Form for adding a method.
     */
    public function create(): View
    {
        $this->authorize('create', PaymentMethodRecord::class);

        return view('admin.payment-methods.create', [
            'method' => null,
            'types' => PaymentMethodType::cases(),
        ]);
    }

    /**
     * Persist a new method.
     *
     * The key is accepted here and only here; afterwards it is immutable,
     * because `payments.method` and every order snapshot reference it as text.
     *
     * A new method starts inactive. Business rule 8 only ever offers a method
     * that is active *and* complete, but a method that has just been typed in is
     * the one most likely to still be wrong, so making activation a deliberate
     * second step is the cheap way to keep a half-finished account number away
     * from participants.
     */
    public function store(StorePaymentMethodRequest $request): RedirectResponse
    {
        $this->authorize('create', PaymentMethodRecord::class);

        $data = $request->paymentMethodData();

        $method = DB::transaction(function () use ($request, $data): PaymentMethodRecord {
            $method = new PaymentMethodRecord;

            $method->key = $request->key();
            $method->label = $data['label'];
            $method->type = $data['type'];
            $method->account = $data['account'];
            $method->steps = $data['steps'];
            $method->is_dummy = $data['is_dummy'];
            $method->is_active = false;
            $method->sort_order = $this->nextSortOrder();
            $method->updated_by = $request->user()->getKey();

            $method->save();

            // Stored after the row exists so the filename can be namespaced by
            // the method id and a failed upload cannot leave a half-built row.
            if ($request->hasFile('qr_image')) {
                $this->images->store($method, $request->file('qr_image'));
            }

            $this->audit->record($method, AuditAction::PaymentMethodCreated, $request->user(), [
                'key' => $method->key,
                'label' => $method->label,
                'type' => $method->type->value,
                'is_dummy' => (bool) $method->is_dummy,
                'account' => $method->account,
                'steps' => $method->steps,
            ]);

            return $method;
        });

        return redirect()
            ->route('admin.payment-methods.index')
            ->with('success', "Metode {$method->label} berhasil ditambahkan. Metode baru belum ditawarkan: aktifkan setelah detailnya dipastikan benar.");
    }

    /**
     * Form for editing global details.
     */
    public function edit(PaymentMethodRecord $paymentMethod): View
    {
        $this->authorize('updateDetails', $paymentMethod);

        return view('admin.payment-methods.edit', [
            'method' => $paymentMethod,
            'types' => PaymentMethodType::cases(),
        ]);
    }

    /**
     * Update global details.
     *
     * Changes apply to new orders only: every existing order keeps the
     * instructions it snapshotted at creation, so editing an account number
     * cannot redirect a participant who has already been told where to pay.
     */
    public function update(
        UpdatePaymentMethodRequest $request,
        PaymentMethodRecord $paymentMethod,
    ): RedirectResponse {
        // Authorisation already ran in the form request, but it is repeated here
        // so the controller does not depend on that detail.
        $this->authorize('updateDetails', $paymentMethod);

        $data = $request->paymentMethodData();

        $before = [
            'label' => $paymentMethod->label,
            'type' => $paymentMethod->type->value,
            'account' => $paymentMethod->account ?? [],
            'steps' => $paymentMethod->steps ?? [],
            'is_dummy' => (bool) $paymentMethod->is_dummy,
            'qr_image_path' => $paymentMethod->qr_image_path,
        ];

        DB::transaction(function () use ($request, $paymentMethod, $data, $before): void {
            $paymentMethod->label = $data['label'];
            $paymentMethod->type = $data['type'];
            $paymentMethod->account = $data['account'];
            $paymentMethod->steps = $data['steps'];
            $paymentMethod->is_dummy = $data['is_dummy'];
            $paymentMethod->updated_by = $request->user()->getKey();
            $paymentMethod->save();

            if ($request->hasFile('qr_image')) {
                $this->images->store($paymentMethod, $request->file('qr_image'));
            } elseif ($request->boolean('remove_qr_image') && filled($paymentMethod->qr_image_path)) {
                // Explicit removal: the file goes, the column is cleared. Only
                // reachable when the request kept the field, so a QRIS method
                // cannot end up with neither an image nor a merchant reference
                // (validated in UpdatePaymentMethodRequest).
                $this->images->deleteFile($paymentMethod->qr_image_path);
                $paymentMethod->qr_image_path = null;
                $paymentMethod->save();
            }

            $this->audit->record($paymentMethod, AuditAction::PaymentMethodUpdated, $request->user(), [
                'key' => $paymentMethod->key,
                'before' => $before,
                'after' => [
                    'label' => $paymentMethod->label,
                    'type' => $paymentMethod->type->value,
                    'account' => $paymentMethod->account,
                    'steps' => $paymentMethod->steps,
                    'is_dummy' => (bool) $paymentMethod->is_dummy,
                    'qr_image_path' => $paymentMethod->qr_image_path,
                ],
            ]);
        });

        return redirect()
            ->route('admin.payment-methods.index')
            ->with('success', "Metode {$paymentMethod->label} berhasil diperbarui. Instruksi pada pesanan lama tidak berubah.");
    }

    /**
     * Activate or deactivate one method for new orders, globally.
     *
     * The target state is submitted explicitly rather than inferred by flipping
     * the current value, so a double click or a resubmitted form cannot silently
     * turn the method back on.
     */
    public function toggle(Request $request, PaymentMethodRecord $paymentMethod): RedirectResponse
    {
        $this->authorize('toggle', $paymentMethod);

        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $target = (bool) $data['is_active'];

        if ($paymentMethod->is_active === $target) {
            return back()->with('success', "Metode {$paymentMethod->label} sudah dalam status tersebut.");
        }

        $paymentMethod->is_active = $target;
        $paymentMethod->updated_by = $request->user()->getKey();
        $paymentMethod->save();

        $this->audit->record(
            $paymentMethod,
            $target ? AuditAction::PaymentMethodActivated : AuditAction::PaymentMethodDeactivated,
            $request->user(),
            [
                'key' => $paymentMethod->key,
                'label' => $paymentMethod->label,
            ],
        );

        // Activating an unfinished method is allowed but does nothing yet, and
        // saying so here is the difference between a puzzle and an explanation.
        if ($target && ! $paymentMethod->isConfigured()) {
            return back()->with('warning', sprintf(
                'Metode %s diaktifkan, tetapi belum ditawarkan kepada peserta karena belum lengkap: %s.',
                $paymentMethod->label,
                implode(', ', $paymentMethod->missingDetails()),
            ));
        }

        return back()->with(
            'success',
            $target
                ? "Metode {$paymentMethod->label} diaktifkan untuk pesanan baru."
                : "Metode {$paymentMethod->label} dinonaktifkan untuk pesanan baru.",
        );
    }

    /**
     * Delete a method that nothing references.
     *
     * `payments.method` and `orders.payment_instructions_snapshot` both store
     * the method key as plain text, so no foreign key would stop a delete from
     * orphaning them. Refusing is therefore the only safe answer once history
     * exists; deactivating is what the operator usually wants anyway, and the
     * list screen offers it next to this action.
     */
    public function destroy(Request $request, PaymentMethodRecord $paymentMethod): RedirectResponse
    {
        $this->authorize('delete', $paymentMethod);

        $label = $paymentMethod->label;

        if ($paymentMethod->hasHistoricalDependencies()) {
            return redirect()
                ->route('admin.payment-methods.index')
                ->with('error', "Metode {$label} masih dirujuk pembayaran atau snapshot pesanan dan tidak dapat dihapus. Nonaktifkan saja agar tidak lagi ditawarkan.");
        }

        $metadata = [
            'key' => $paymentMethod->key,
            'label' => $label,
            'type' => $paymentMethod->type->value,
            'account' => $paymentMethod->account ?? [],
            'steps' => $paymentMethod->steps ?? [],
            'is_dummy' => (bool) $paymentMethod->is_dummy,
        ];

        DB::transaction(function () use ($request, $paymentMethod, $metadata): void {
            // The QR image is a file, so it is removed explicitly. Per-event
            // overrides go with the row through the foreign key; the count is
            // recorded because they are not otherwise recoverable from the trail.
            $imagePath = $paymentMethod->qr_image_path;
            $overrides = $paymentMethod->eventStates()->count();

            $paymentMethod->delete();

            $this->images->deleteFile($imagePath);

            $this->audit->record(
                $paymentMethod,
                AuditAction::PaymentMethodDeleted,
                $request->user(),
                $metadata + ['event_overrides_removed' => $overrides],
            );
        });

        return redirect()
            ->route('admin.payment-methods.index')
            ->with('success', "Metode {$label} berhasil dihapus.");
    }

    /**
     * Activation screen for one event (Phase 12B scope D).
     *
     * Shows every method with its global state, this event's override if there
     * is one, and what a participant would actually be offered. The last of
     * those is the only way to see the combined effect of global status,
     * completeness, and override without reading three places.
     */
    public function forEvent(Event $event): View
    {
        $this->authorize('viewAny', PaymentMethodRecord::class);

        $methods = PaymentMethodRecord::query()
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();

        return view('admin.payment-methods.event', [
            'event' => $event,
            'methods' => $methods,
            'overrides' => $methods
                ->mapWithKeys(fn (PaymentMethodRecord $method) => [
                    $method->getKey() => $this->registry->overrideFor($event, $method),
                ])
                ->all(),
            'offerableKeys' => $this->registry->availableForEvent($event)->keys()->all(),
        ]);
    }

    /**
     * Enable or disable one method for one event.
     *
     * Writes an override row for this event only. The global row is not touched,
     * which is what makes "disabled for Event A, still offered for Event B" true
     * without any other bookkeeping.
     */
    public function toggleForEvent(
        Request $request,
        Event $event,
        PaymentMethodRecord $paymentMethod,
    ): RedirectResponse {
        // Same ability as the global toggle: it is the same operational decision
        // made at a narrower scope.
        $this->authorize('toggle', $paymentMethod);

        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $target = (bool) $data['is_active'];

        $override = EventPaymentMethod::query()->firstOrNew([
            'event_id' => $event->getKey(),
            'payment_method_id' => $paymentMethod->getKey(),
        ]);

        if ($override->exists && (bool) $override->is_active === $target) {
            return back()->with(
                'success',
                "Metode {$paymentMethod->label} sudah dalam status tersebut untuk {$event->name}.",
            );
        }

        $override->is_active = $target;
        $override->updated_by = $request->user()->getKey();
        $override->save();

        $this->audit->record($paymentMethod, AuditAction::PaymentMethodEventToggled, $request->user(), [
            'key' => $paymentMethod->key,
            'label' => $paymentMethod->label,
            'event_id' => $event->getKey(),
            'event_name' => $event->name,
            'is_active' => $target,
        ]);

        return back()->with('success', sprintf(
            '%s %s untuk event %s.',
            $paymentMethod->label,
            $target ? 'diaktifkan' : 'dinonaktifkan',
            $event->name,
        ));
    }

    /**
     * Drop this event's override so the method follows the global flag again.
     *
     * Without this, an override could never be undone: an explicit "active" and
     * "follows global" look the same on screen while behaving differently the
     * next time the global flag changes.
     */
    public function clearEventOverride(
        Request $request,
        Event $event,
        PaymentMethodRecord $paymentMethod,
    ): RedirectResponse {
        $this->authorize('toggle', $paymentMethod);

        $override = EventPaymentMethod::query()
            ->where('event_id', $event->getKey())
            ->where('payment_method_id', $paymentMethod->getKey())
            ->first();

        if ($override === null) {
            return back()->with(
                'success',
                "Metode {$paymentMethod->label} memang sudah mengikuti pengaturan global untuk {$event->name}.",
            );
        }

        $override->delete();

        $this->audit->record($paymentMethod, AuditAction::PaymentMethodEventInherited, $request->user(), [
            'key' => $paymentMethod->key,
            'label' => $paymentMethod->label,
            'event_id' => $event->getKey(),
            'event_name' => $event->name,
            'global_is_active' => (bool) $paymentMethod->is_active,
        ]);

        return back()->with(
            'success',
            "Metode {$paymentMethod->label} kembali mengikuti pengaturan global untuk {$event->name}.",
        );
    }

    /**
     * Sort position for a newly created method.
     *
     * Appended after every existing method rather than given an arbitrary or
     * reused number. This column orders the catalog both in the admin list and
     * in the participant's picker, so "last" is the only position that cannot
     * silently push an already-offered method further down. Unused numbers are
     * left alone: only the relative order matters, so adding one method never
     * renumbers the others. The seeder numbers the configured methods from 0, so
     * an untouched catalog simply continues from its last position.
     */
    private function nextSortOrder(): int
    {
        return (int) PaymentMethodRecord::query()->max('sort_order') + 1;
    }
}
