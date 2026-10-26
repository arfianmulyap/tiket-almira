<?php

namespace App\Models;

use App\Enums\AuditAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One entry in the append-only audit trail (PRD FR-12).
 */
class AuditLog extends Model
{
    /**
     * Rows are written once and never modified, so there is no `updated_at`
     * column to maintain.
     */
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => AuditAction::class,
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Who acted. The expiry sweep and other scheduled work has no user.
     */
    public function actorName(): string
    {
        return filled($this->user_name) ? (string) $this->user_name : 'Sistem';
    }

    /**
     * Human readable name of the object the action was recorded against.
     *
     * The relation is usually eager loaded by the audit screen; this method
     * also degrades gracefully when the subject has since been deleted, in
     * which case the stored morph type and id are all that remain.
     */
    public function subjectLabel(): string
    {
        $subject = $this->auditable;

        return match (true) {
            $subject instanceof Order => $subject->order_reference,
            $subject instanceof Role => $subject->name,
            $subject instanceof Permission => (string) ($subject->label ?: $subject->name),
            $subject instanceof User => $subject->name.' ('.$subject->email.')',
            $subject instanceof PaymentMethod => $subject->label,
            $subject instanceof Ticket => 'Tiket #'.$subject->getKey(),
            default => $subject !== null
                ? class_basename($subject).' #'.$this->auditable_id
                : class_basename((string) $this->auditable_type).' #'.$this->auditable_id,
        };
    }

    /**
     * Where the subject can be inspected, when the panel has a screen for it.
     *
     * Only used to render a link; the destination still enforces its own
     * authorisation, and the audit screen itself is Super Admin only.
     */
    public function subjectUrl(): ?string
    {
        $subject = $this->auditable;

        return match (true) {
            $subject instanceof Order => route('admin.orders.show', $subject),
            $subject instanceof Role => $subject->isProtected()
                ? null
                : route('admin.roles.edit', $subject),
            $subject instanceof PaymentMethod => route('admin.payment-methods.edit', $subject),
            $subject instanceof User => route('admin.accounts.index'),
            $subject instanceof Permission => route('admin.permissions.index'),
            default => null,
        };
    }
}
