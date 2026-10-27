<?php

namespace App\Models;

use App\Enums\PaymentMethodType;
use App\Support\PaymentMethod as PaymentMethodValue;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * A configurable manual payment method (Phase 12, extended in Phase 12B).
 *
 * The key is the stable identifier referenced by `payments.method` and by
 * order snapshots. It is never editable through the panel; only the label,
 * type, instructions, active flag, and placeholder flag can change.
 *
 * `type` decides which detail fields the method must state (see
 * App\Enums\PaymentMethodType). Global details live here; whether a method is
 * *offered* for a particular event is a separate override stored in
 * `event_payment_methods`, so disabling a method for one event cannot affect
 * another.
 */
#[Fillable(['key', 'label', 'type', 'is_active', 'is_dummy', 'account', 'steps', 'qr_image_path', 'sort_order'])]
class PaymentMethod extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PaymentMethodType::class,
            'is_active' => 'boolean',
            'is_dummy' => 'boolean',
            'account' => 'array',
            'steps' => 'array',
            'sort_order' => 'integer',
        ];
    }

    /**
     * The admin who last changed this method's configuration.
     *
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Per-event activation overrides for this method (Phase 12B).
     *
     * Only events that disagree with the global flag have a row here.
     *
     * @return HasMany<EventPaymentMethod, $this>
     */
    public function eventStates(): HasMany
    {
        return $this->hasMany(EventPaymentMethod::class);
    }

    /**
     * Present the row through the typed value object the rest of the
     * application reads.
     */
    public function toValue(): PaymentMethodValue
    {
        return PaymentMethodValue::fromRecord([
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type->value,
            'is_active' => $this->is_active,
            'is_dummy' => $this->is_dummy,
            'account' => $this->account ?? [],
            'steps' => $this->steps ?? [],
            'qr_image_url' => $this->qrImageUrl(),
        ]);
    }

    /**
     * Snapshot form written onto a new order.
     *
     * @return array<string, mixed>
     */
    public function toSnapshot(): array
    {
        return $this->toValue()->toSnapshot();
    }

    /**
     * Value a stored detail row holds for one field of this method's type.
     *
     * Delegates to the value object so the definition of "which row is the
     * account number" exists once, and the participant-facing availability rule
     * cannot disagree with what the admin screen shows.
     */
    public function detailValue(string $field): ?string
    {
        return $this->toValue()->detailValue($field);
    }

    /**
     * The bank, provider, or merchant a participant pays into.
     */
    public function providerValue(): ?string
    {
        return $this->toValue()->providerValue();
    }

    /**
     * Labels of the required details this method is still missing.
     *
     * Drives the "Konfigurasi" column. A method may legitimately be saved as an
     * unfinished draft, but an unfinished method is never offered to a
     * participant, so the list states exactly what is absent.
     *
     * @return array<int, string>
     */
    public function missingDetails(): array
    {
        return $this->toValue()->missingDetails();
    }

    /**
     * Whether every required detail for this method's type is present.
     */
    public function isConfigured(): bool
    {
        return $this->toValue()->isConfigured();
    }

    /**
     * Public URL of the QR merchant image, or null when none is uploaded.
     *
     * The disk is read from config rather than hardcoded so the URL follows the
     * image to wherever it is actually stored: the local public disk in
     * development, or object storage in an environment with no persistent
     * filesystem. The image must stay publicly readable, because a participant
     * has to load it in order to scan the code.
     */
    public function qrImageUrl(): ?string
    {
        if (blank($this->qr_image_path)) {
            return null;
        }

        return Storage::disk((string) config('payments.qr_image.disk', 'public'))
            ->url($this->qr_image_path);
    }

    /**
     * Whether removing this method would erase something payment history needs.
     *
     * `payments.method` and `orders.payment_instructions_snapshot` both store
     * the method *key* as a plain string, so no foreign key protects them. The
     * check therefore has to be explicit: a method that appears in either is
     * only deactivatable, never deletable (PRD business rule 12 — history must
     * stay resolvable).
     */
    public function hasHistoricalDependencies(): bool
    {
        return self::keyIsUsedByHistory((string) $this->key);
    }

    /**
     * Whether a key already appears in payment history.
     *
     * Static because the create form has to answer the same question before a
     * record exists: reusing a key that an old payment or an old order snapshot
     * still points at would silently re-attach that history to a brand-new
     * method.
     */
    public static function keyIsUsedByHistory(string $key): bool
    {
        if (trim($key) === '') {
            return false;
        }

        if (Payment::query()->where('method', $key)->exists()) {
            return true;
        }

        // JSON_CONTAINS with a one-key object: snapshot rows carry more than
        // the key, and a subset match is exactly what is wanted here.
        return Order::query()
            ->whereJsonContains('payment_instructions_snapshot', [['key' => $key]])
            ->exists();
    }
}
