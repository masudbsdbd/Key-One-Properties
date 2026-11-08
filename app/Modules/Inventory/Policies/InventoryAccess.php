<?php

namespace App\Modules\Inventory\Policies;

use App\Models\MaterialBorrow;
use App\Models\MaterialTransfer;
use App\Models\Project;
use App\Models\User;
use App\Models\Warehouse;

class InventoryAccess
{
    public static function warehouse(User $user, Warehouse $warehouse, string $permission): bool
    {
        return $user->can($permission) && $warehouse->isVisibleTo($user) && $warehouse->is_active;
    }

    public static function issueTo(User $user, Warehouse $warehouse, Project $project): bool
    {
        return self::warehouse($user, $warehouse, 'materials.issue') && $project->isVisibleTo($user)
            && ($warehouse->project_id === null || $warehouse->project_id === $project->id);
    }

    public static function borrow(User $user, MaterialBorrow $borrow, string $action): bool
    {
        return match ($action) {
            'view' => $user->can('materials.view') && $borrow->isVisibleTo($user),
            'approve' => $user->can('materials.borrow.approve') && $borrow->sourceProject->isVisibleTo($user),
            'issue' => $user->can('materials.borrow.issue') && $borrow->sourceProject->isVisibleTo($user),
            'return' => $user->can('materials.borrow.return') && $borrow->isVisibleTo($user),
            'convert' => $user->can('materials.borrow.convert') && $borrow->sourceProject->isVisibleTo($user) && $borrow->destinationProject->isVisibleTo($user),
            'cancel' => $borrow->requested_by === $user->id || ($user->can('materials.borrow.approve') && $borrow->sourceProject->isVisibleTo($user)),
            default => false,
        };
    }

    public static function transfer(User $user, MaterialTransfer $transfer, string $action): bool
    {
        return match ($action) {
            'view' => $user->can('materials.view') && $transfer->isVisibleTo($user),
            'approve' => $user->can('materials.transfer.approve') && $transfer->sourceWarehouse->isVisibleTo($user),
            'post' => $user->can('materials.transfer.post') && $transfer->isVisibleTo($user),
            'reverse' => $user->can('materials.movement.reverse') && $transfer->sourceWarehouse->isVisibleTo($user) && $transfer->destinationWarehouse->isVisibleTo($user),
            'cancel' => $transfer->requested_by === $user->id || ($user->can('materials.transfer.approve') && $transfer->sourceWarehouse->isVisibleTo($user)),
            default => false,
        };
    }
}
