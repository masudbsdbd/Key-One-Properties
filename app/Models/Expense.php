<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Expense extends Model
{
    use HasAttachments, HasPublicId;

    public const SCOPES = ['general' => 'General', 'project' => 'Project'];

    public const STATUSES = ['draft' => 'Draft', 'submitted' => 'Submitted', 'approved' => 'Approved', 'paid' => 'Paid', 'rejected' => 'Rejected', 'reversed' => 'Reversed'];

    public const EDITABLE = ['draft', 'rejected'];

    public const METHODS = SupplierPayment::METHODS;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['expense_date' => 'date', 'paid_on' => 'date', 'amount' => 'decimal:2',
            'submitted_at' => 'datetime', 'approved_at' => 'datetime', 'paid_at' => 'datetime', 'reversed_at' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function workPackage(): BelongsTo
    {
        return $this->belongsTo(WorkPackage::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(ExpenseAction::class)->orderBy('id');
    }

    /** General expenses need expenses.view.general; project expenses need expenses.view.project and project access. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $general = $user->can('expenses.view.general');
        $project = $user->can('expenses.view.project');

        return $query->where(function (Builder $q) use ($general, $project, $user) {
            if ($general) {
                $q->orWhere('scope', 'general');
            }
            if ($project) {
                $q->orWhere(fn (Builder $p) => $p->where('scope', 'project')->whereHas('project', fn (Builder $pr) => $pr->visibleTo($user)));
            }
            if (! $general && ! $project) {
                $q->whereRaw('1 = 0');
            }
        });
    }

    public function isVisibleTo(User $user): bool
    {
        return $this->scope === 'general'
            ? $user->can('expenses.view.general')
            : $user->can('expenses.view.project') && $this->project->isVisibleTo($user);
    }

    public function canBeCreatedBy(User $user): bool
    {
        return $user->can('expenses.create.'.$this->scope) && ($this->scope === 'general' || $this->project->isVisibleTo($user));
    }
}
