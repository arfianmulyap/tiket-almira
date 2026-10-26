<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;

/**
 * Authorisation for the audit trail (Phase 12).
 *
 * `audit_logs` spans every sensitive action in the panel: payment decisions,
 * access-control changes, account invitations, configuration edits. The plan
 * requires the reader to be Super Admin only, so this is intentionally not a
 * grantable permission. There is no write ability: rows are appended by
 * AuditLogger and never edited or deleted through the panel.
 */
class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        return $user->isSuperAdmin();
    }
}
