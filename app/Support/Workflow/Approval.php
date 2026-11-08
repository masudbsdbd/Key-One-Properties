<?php

namespace App\Support\Workflow;

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class Approval
{
    /**
     * Block approving your own document unless self-approval is enabled.
     */
    public static function ensureNotSelf(User $approver, ?int $createdBy, string $what = 'this document'): void
    {
        if (! config('keyone.allow_self_approval') && $createdBy !== null && $approver->id === $createdBy && ! $approver->isSuperAdmin()) {
            throw ValidationException::withMessages(['approval' => "You cannot approve {$what} you created."]);
        }
    }

    /**
     * @param  array<int, string>|string  $allowed
     */
    public static function ensureStatus(string $current, array|string $allowed, string $message = 'This action is not allowed in the current status.'): void
    {
        if (! in_array($current, (array) $allowed, true)) {
            throw new ConflictHttpException($message);
        }
    }
}
