<?php

namespace App\Modules\Sales\Controllers\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

trait AuthorizesProjectRecords
{
    /** Permission plus project visibility of the record; 403 otherwise. */
    protected function authorizeRecord(Request $request, string|array $permission, Model $record): void
    {
        $user = $request->user();
        $allowed = is_array($permission) ? $user->canAny($permission) : $user->can($permission);
        abort_unless($allowed && $record->isVisibleTo($user), 403);
    }
}
