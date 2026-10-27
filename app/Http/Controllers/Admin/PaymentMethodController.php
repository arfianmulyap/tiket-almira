<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePaymentMethodRequest;
use App\Models\PaymentMethod as PaymentMethodRecord;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Payment settings (Phase 12).
 *
 * Toggling a method follows the grantable `payment-methods.manage`
 * permission. Editing the recipient details is Super Admin only, because a
 * changed account number changes where participants send money. Both rules
 * live in PaymentMethodPolicy rather than in the routes.
 *
 * Nothing here touches existing orders. Each order carries the instructions
 * that were shown when it was created.
 */
class PaymentMethodController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        $this->authorize('viewAny', PaymentMethodRecord::class);

        return view('admin.payment-methods.index', [
            'methods' => PaymentMethodRecord::query()
                ->with('updatedBy')
                ->orderBy('sort_order')
                ->orderBy('label')
                ->get(),
        ]);
    }

    /**
     * Activate or deactivate one method.
     *
     * The target state is submitted explicitly rather than inferred by
     * flipping the current value, so a double click or a resubmitted form
     * cannot silently turn the method back on.
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

        return back()->with(
            'success',
            $target
                ? "Metode {$paymentMethod->label} diaktifkan untuk pesanan baru."
                : "Metode {$paymentMethod->label} dinonaktifkan untuk pesanan baru.",
        );
    }

    public function edit(PaymentMethodRecord $paymentMethod): View
    {
        $this->authorize('updateDetails', $paymentMethod);

        return view('admin.payment-methods.edit', [
            'method' => $paymentMethod,
        ]);
    }

    public function update(
        UpdatePaymentMethodRequest $request,
        PaymentMethodRecord $paymentMethod,
    ): RedirectResponse {
        // Authorisation already ran in the form request, but it is repeated
        // here so the controller does not depend on that detail.
        $this->authorize('updateDetails', $paymentMethod);

        $data = $request->paymentMethodData();

        $before = [
            'label' => $paymentMethod->label,
            'account' => $paymentMethod->account ?? [],
            'steps' => $paymentMethod->steps ?? [],
            'is_dummy' => $paymentMethod->is_dummy,
        ];

        $paymentMethod->label = $data['label'];
        $paymentMethod->account = $data['account'];
        $paymentMethod->steps = $data['steps'];
        $paymentMethod->is_dummy = $data['is_dummy'];
        $paymentMethod->updated_by = $request->user()->getKey();
        $paymentMethod->save();

        $this->audit->record($paymentMethod, AuditAction::PaymentMethodUpdated, $request->user(), [
            'key' => $paymentMethod->key,
            'before' => $before,
            'after' => [
                'label' => $paymentMethod->label,
                'account' => $paymentMethod->account,
                'steps' => $paymentMethod->steps,
                'is_dummy' => $paymentMethod->is_dummy,
            ],
        ]);

        return redirect()
            ->route('admin.payment-methods.index')
            ->with('success', "Metode {$paymentMethod->label} berhasil diperbarui.");
    }
}
