<?php

namespace App\Modules\Expenses\Policies;

use App\Models\Expense;
use App\Models\User;
use App\Support\Attachments\AttachableRegistry;
use Illuminate\Support\Facades\Gate;

class ExpenseAccess
{
    public static function register(): void
    {
        Gate::define('expense:files-view', fn (User $u, Expense $e) => $e->isVisibleTo($u));
        Gate::define('expense:files-manage', fn (User $u, Expense $e) => $e->isVisibleTo($u) && $e->canBeCreatedBy($u) && in_array($e->status, ['draft', 'rejected', 'submitted'], true));
        AttachableRegistry::register('expense', Expense::class, 'expense:files-view', 'expense:files-manage');
    }
}
