<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes the audit trail required by PRD FR-12.
 *
 * The acting user is passed in explicitly rather than read from auth() inside
 * this class: the expiry sweep runs from the scheduler with no authenticated
 * user, and tests must be able to record a decision for a given admin without
 * logging anyone in.
 */
class AuditLogger
{
    /**
     * Append one entry to the trail.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        Model $auditable,
        AuditAction $action,
        ?User $actor = null,
        array $metadata = [],
    ): AuditLog {
        $log = new AuditLog;

        $log->auditable_type = $auditable->getMorphClass();
        $log->auditable_id = $auditable->getKey();
        $log->action = $action;
        $log->user_id = $actor?->getKey();

        // Copied, not joined: the trail must stay readable even if the admin
        // account is deleted later.
        $log->user_name = $actor?->name;

        $log->metadata = $metadata === [] ? null : $metadata;
        $log->created_at = now();

        $log->save();

        return $log;
    }
}
